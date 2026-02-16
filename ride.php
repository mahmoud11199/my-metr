<?php
session_start();
require_once 'config.php';
$page_title = 'بدء الرحلة';
include 'header.php';

/*
  مَلحوظة: هذا الملف يحتوي الآن أيضاً على رمز خادمي بسيط للتعامل مع:
    - ?action=track_batch  (POST)  => استقبال دفعات تتبع أثناء الرحلة
    - ?action=telemetry    (POST)  => استقبال أخطاء/سجلات telemetry من العميل
  هذه الEndpoints مبسطة — تأكد من تعديلها/نقلها إلى ملفات منفصلة (ride_track.php, ride_telemetry.php)
  في بيئة الإنتاج حسب الحاجة.
*/

/* --- CSRF token --- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* --- تحقق الجلسة والدور: مستخدم فقط --- */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  http_response_code(403);
  echo "<p>🚫 صلاحية غير كافية. هذه الصفحة للمستخدم فقط.</p>";
  include 'footer.php';
  exit;
}
$user_id = (int)$_SESSION['user_id'];

/* --- إعدادات العرض: أسعار --- */
$km_price = 0.0;
if ($stmt = $conn->prepare("SELECT km_price FROM user_preferences WHERE user_id = ? LIMIT 1")) {
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  if (isset($row['km_price'])) $km_price = (float)$row['km_price'];
  $stmt->close();
}
$base_fare = 5.0; $minute_price = 1.0;
if ($res = $conn->query("SELECT * FROM ride_settings ORDER BY created_at DESC LIMIT 1")) {
  $settings = $res->fetch_assoc();
  if ($settings) {
    $base_fare = isset($settings['min_base_fare']) ? (float)$settings['min_base_fare'] : $base_fare;
    $minute_price = isset($settings['min_minute_price']) ? (float)$settings['min_minute_price'] : $minute_price;
  }
}

/* ----------------
   Handlers for AJAX actions within same file (track_batch, telemetry)
   ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
  // قراءة JSON
  $action = $_GET['action'];
  // Simple CSRF check for these endpoints
  $headers = getallheaders();
  $client_csrf = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? null;
  if (!$client_csrf || !hash_equals($_SESSION['csrf_token'], $client_csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token missing أو غير صالح']);
    exit;
  }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON غير صالح']);
    exit;
  }

  if ($action === 'track_batch') {
    // المتوقع: { ride_id, points: [{lat,lng,timestamp}], last_point_lat, last_point_lng }
    $ride_id = isset($data['ride_id']) ? (int)$data['ride_id'] : 0;
    $points = $data['points'] ?? [];
    // قيود أساسية
    if ($ride_id <= 0 || !is_array($points) || count($points) === 0) {
      http_response_code(400);
      echo json_encode(['error'=>'محتوى غير صالح']);
      exit;
    }
    // حماية ضد حمل كبير
    $max_points = 500; // لا تقبل دفعة أكبر من هذا
    if (count($points) > $max_points) {
      http_response_code(413);
      echo json_encode(['error'=>'الدفعة كبيرة جداً']);
      exit;
    }

    // 1) تحقق من أن ride_id ملكٌ للمستخدم (أو أن السجل ما زال فعّال) -- مثال بسيط:
    $stmt = $conn->prepare("SELECT id, user_id, status FROM rides WHERE id=? LIMIT 1");
    if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'DB error']); exit; }
    $stmt->bind_param("i",$ride_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res->fetch_assoc();
    $stmt->close();
    if (!$r) {
      http_response_code(404);
      echo json_encode(['error'=>'الرحلة غير موجودة']);
      exit;
    }
    // السماح فقط للمستخدم صاحب الرحلة أو للسائق حسب منطقك — هنا نتحقق من مالك الرحلة
    if ((int)$r['user_id'] !== $user_id) {
      http_response_code(403);
      echo json_encode(['error'=>'غير مصرح بإرسال بيانات لهذه الرحلة']);
      exit;
    }

    // 2) Validate points: لا نقاط بقفزات غير منطقية (مثال بسيط: لو السرعة المحسوبة بين نقطتين > 300 كم/س حذفها)
    function haversine_km($lat1,$lon1,$lat2,$lon2){
      $R=6371;
      $dLat=deg2rad($lat2-$lat1);
      $dLon=deg2rad($lon2-$lon1);
      $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
      $c = 2 * atan2(sqrt($a), sqrt(1-$a));
      return $R * $c;
    }

    $filtered = [];
    $max_speed_kmh = 300; // عتبة صارمة
    $prev = null;
    foreach ($points as $pt) {
      if (!isset($pt['lat']) || !isset($pt['lng']) || !isset($pt['timestamp'])) continue;
      $lat = floatval($pt['lat']);
      $lng = floatval($pt['lng']);
      $ts = strtotime($pt['timestamp']);
      if ($ts === false) $ts = time();
      if ($prev) {
        $dt = max(1, $ts - $prev['ts']); // بالثواني
        $dist = haversine_km($prev['lat'],$prev['lng'],$lat,$lng);
        $speed = ($dist / ($dt/3600)); // كم/س
        if ($speed > $max_speed_kmh) {
          // تجاهل النقطة كقفزة غير منطقية
          continue;
        }
      }
      $filtered[] = ['lat'=>$lat,'lng'=>$lng,'timestamp'=>date('c',$ts)];
      $prev = ['lat'=>$lat,'lng'=>$lng,'ts'=>$ts];
    }

    if (count($filtered) === 0) {
      echo json_encode(['message'=>'لا نقاط بعد التصفية']);
      exit;
    }

    // 3) حفظ الدفعة في جدول ride_tracking (مثال)
    $stmt = $conn->prepare("INSERT INTO ride_tracking (ride_id, user_id, batch_json, created_at) VALUES (?,?,?,NOW())");
    if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'DB insert error']); exit; }
    $batch_json = json_encode($filtered);
    $stmt->bind_param("iis", $ride_id, $user_id, $batch_json);
    $ok = $stmt->execute();
    if (!$ok) {
      http_response_code(500); echo json_encode(['error'=>'فشل الحفظ في DB']); exit;
    }
    $stmt->close();

    echo json_encode(['ok'=>true,'saved_points'=>count($filtered)]);
    exit;
  }

  if ($action === 'telemetry') {
    // متوقع: { type: 'watch_error', message, code, extra }
    $type = $data['type'] ?? 'unknown';
    $message = $data['message'] ?? '';
    $code = $data['code'] ?? null;
    $extra = isset($data['extra']) ? json_encode($data['extra']) : null;
    $stmt = $conn->prepare("INSERT INTO ride_telemetry (user_id, type, message, code, extra, created_at) VALUES (?,?,?,?,?,NOW())");
    if ($stmt) {
      $stmt->bind_param("issis", $user_id, $type, $message, $code, $extra);
      $stmt->execute();
      $stmt->close();
    }
    echo json_encode(['ok'=>true]);
    exit;
  }

  // action غير معروف
  http_response_code(400);
  echo json_encode(['error'=>'action غير معروف']);
  exit;
}

/* -------------------
   نهاية handlers
   ------------------- */

?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8" />
  <title>بدء الرحلة</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    #map { height: 420px; margin-bottom: 16px; }
    .panel { background:#fff; border:1px solid #ddd; border-radius:8px; padding:12px; margin-bottom:12px; }
    .actions { margin-bottom:12px; }
    .actions button { padding:8px 12px; border-radius:6px; border:none; cursor:pointer; margin-inline-end:8px; font-weight:600; }
    .start { background:#28a745; color:#fff; }
    .end { background:#dc3545; color:#fff; }
    .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
    .item { background:#f9f9f9; border:1px solid #eee; border-radius:6px; padding:8px; }
    .label { color:#555; font-size:13px; }
    .value { font-weight:700; font-size:16px; }
    /* لوحة الدفع */
    #paymentPanel button { margin-top:6px; padding:6px 10px; border-radius:6px; border:none; cursor:pointer; }
    #payCashBtn { background:#28a745; color:#fff; }
    #verifyUserBtn { background:#007bff; color:#fff; }
    #confirmTransferBtn { background:#ffc107; color:#000; }
    #gpsStatus { font-weight:700; }
    #lastUpdate { font-size:12px; color:#666; }
  </style>
</head>
<body>
  <h2>🚕 العداد الذكي للرحلة</h2>

  <div id="map" class="panel"></div>

  <div class="actions">
    <button id="startBtn" class="start">▶️ بدء الرحلة</button>
    <button id="endBtn" class="end">⏹️ إنهاء الرحلة</button>
  </div>

  <div id="livePanel" class="panel" style="display:none;">
    <h3>📊 العداد الحي</h3>
    <div class="grid">
      <div class="item"><div class="label">⏱️ الوقت (دقيقة):</div><div class="value" id="uiTime">0</div></div>
      <div class="item"><div class="label">📏 المسافة (كم):</div><div class="value" id="uiDistance">0.00</div></div>
      <div class="item"><div class="label">🚀 السرعة (كم/س):</div><div class="value" id="uiSpeed">0.00</div></div>
      <div class="item"><div class="label">💰 تقدير التكلفة:</div><div class="value" id="uiFare">0.00</div></div>
    </div>
    <hr>
    <div class="grid">
      <div class="item"><div class="label">سعر الكيلو:</div><div class="value"><?= number_format($km_price,2) ?></div></div>
      <div class="item"><div class="label">سعر الدقيقة:</div><div class="value"><?= number_format($minute_price,2) ?></div></div>
      <div class="item"><div class="label">سعر الفتح:</div><div class="value"><?= number_format($base_fare,2) ?></div></div>
      <div class="item"><div class="label">رمز المشاركة:</div><div class="value" id="uiShare">—</div></div>
    </div>

    <div style="margin-top:10px;">
      <span id="gpsStatus">GPS: —</span>
      <span id="lastUpdate" style="margin-left:10px;">آخر تحديث: —</span>
    </div>
  </div>

  <div id="report" class="panel"></div>

  <!-- لوحة الدفع الجديدة -->
  <div id="paymentPanel" class="panel" style="margin-top:15px; display:none;">
    <h4>اختيار الدفع</h4>
    <button id="payCashBtn">💵 دفع كاش</button>

    <div style="margin-top:10px;">
      <h5>تحويل لمستخدم آخر</h5>
      <label>ID المستقبل:</label>
      <input type="number" id="toUserId" />
      <label>البريد الإلكتروني:</label>
      <input type="email" id="toUserEmail" />
      <button id="verifyUserBtn">تحقق</button>

      <div id="verifyResult" style="margin-top:8px;"></div>
      <button id="confirmTransferBtn" disabled>تأكيد التحويل</button>
    </div>
  </div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
const KM_PRICE    = <?= json_encode($km_price) ?>;
const MIN_PRICE   = <?= json_encode($minute_price) ?>;
const BASE_FARE   = <?= json_encode($base_fare) ?>;

const STORAGE_KEY = 'ride_state_v2';
function saveRideState(state){ try{ localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }catch(e){ console.warn('localStorage save failed',e); } }
function loadRideState(){ try{ const raw=localStorage.getItem(STORAGE_KEY); return raw?JSON.parse(raw):null; }catch(e){ return null; } }
function clearRideState(){ try{ localStorage.removeItem(STORAGE_KEY); }catch(e){} }

let map, startMarker=null, endMarker=null, routeLine=null;
let rideStarted=false, watchId=null, timer=null;
let startCoords=null, endCoords=null, startTime=null;
let secondsElapsed=0, rideId=null;
const routePoints=[], tracking=[];
const MAX_TRACK=2000; // حد منطقي للتخزين المحلي
const BATCH_SIZE=60;   // إرسال كل 60 نقطة أو كل batchInterval
const BATCH_INTERVAL_MS = 30000; // أو كل 30 ثانية
let batchTimer = null;
let pendingBatch = [];

const geoOptions={enableHighAccuracy:true,timeout:20000,maximumAge:1000};
let awaitingServer=false; // لتعطيل الأزرار أثناء الانتظار

/* ----- Kalman filter بسيط ثنائي المحاور ----- */
function Kalman(lat, lng) {
  // نموذج مبسّط: نطبق فلتر 1D على كل محور
  this.q = 0.00001; // process noise
  this.r = 0.0005;  // measurement noise
  this.x_lat = lat;
  this.x_lng = lng;
  this.p_lat = 1;
  this.p_lng = 1;
}
Kalman.prototype.update = function(lat, lng) {
  // update lat
  // prediction step skipped (no motion model)
  // measurement update
  // K = p/(p+r)
  let Klat = this.p_lat / (this.p_lat + this.r);
  this.x_lat = this.x_lat + Klat * (lat - this.x_lat);
  this.p_lat = (1 - Klat) * this.p_lat + this.q;

  let Klng = this.p_lng / (this.p_lng + this.r);
  this.x_lng = this.x_lng + Klng * (lng - this.x_lng);
  this.p_lng = (1 - Klng) * this.p_lng + this.q;

  return [this.x_lat, this.x_lng];
}

let kalman = null;

/* ----- Douglas-Peucker لتبسيط المسار ----- */
function simplifyDP(points, epsilon) {
  // points: [[lat,lng],...]
  if (!points || points.length < 3) return points.slice();
  function getSqDist(p1, p2) {
    const dLat = p1[0]-p2[0], dLng = p1[1]-p2[1];
    return dLat*dLat + dLng*dLng;
  }
  function getSqSegDist(p, p1, p2) {
    let x = p1[0], y = p1[1], x2 = p2[0], y2 = p2[1];
    let dx = x2 - x, dy = y2 - y;
    if (dx === 0 && dy === 0) {
      return getSqDist(p, p1);
    }
    let t = ((p[0] - x) * dx + (p[1] - y) * dy) / (dx*dx + dy*dy);
    if (t < 0) return getSqDist(p, p1);
    if (t > 1) return getSqDist(p, p2);
    let proj = [x + t*dx, y + t*dy];
    return getSqDist(p, proj);
  }
  function simplifyDPRec(pts, first, last, eps, result) {
    let maxSqDist = eps;
    let index = -1;
    for (let i = first+1; i < last; i++) {
      let sq = getSqSegDist(pts[i], pts[first], pts[last]);
      if (sq > maxSqDist) {
        index = i;
        maxSqDist = sq;
      }
    }
    if (index !== -1) {
      if (index - first > 1) simplifyDPRec(pts, first, index, eps, result);
      result.push(pts[index]);
      if (last - index > 1) simplifyDPRec(pts, index, last, eps, result);
    }
  }
  const result = [points[0]];
  simplifyDPRec(points, 0, points.length-1, epsilon*epsilon, result);
  result.push(points[points.length-1]);
  // result may not be ordered – we need to sort by original index; simpler: iterate to pick those present
  const set = new Set(result.map(p=>p[0]+'|'+p[1]));
  const ordered = points.filter(p=>set.has(p[0]+'|'+p[1]));
  return ordered;
}

/* ----- Helpers ----- */
function distanceKmFromRoute(route){
  if(route.length<2)return 0;
  let m=0;
  for(let i=1;i<route.length;i++){
    m+=L.latLng(route[i-1][0],route[i-1][1]).distanceTo(L.latLng(route[i][0],route[i][1]));
  }
  return m/1000;
}
function updatePolyline(){ 
  if(routeLine){ routeLine.setLatLngs(routePoints); } 
  else { routeLine=L.polyline(routePoints,{color:'#007bff',weight:4}).addTo(map); } 
  if(routePoints.length>1){ try{ map.fitBounds(routeLine.getBounds(), {padding:[50,50]}); }catch(e){} }
}
function updateUI(){ 
  const mins=Math.floor(secondsElapsed/60); 
  document.getElementById('uiTime').textContent=mins; 
  const dist=distanceKmFromRoute(routePoints); 
  document.getElementById('uiDistance').textContent=dist.toFixed(2); 
  const speed=mins>0?(dist/(mins/60)):0; 
  document.getElementById('uiSpeed').textContent=speed.toFixed(2); 
  const fareEst=BASE_FARE+(dist*KM_PRICE)+(mins*MIN_PRICE); 
  document.getElementById('uiFare').textContent=fareEst.toFixed(2); 
}

/* ----- geolocation helpers ----- */
function getPosOnce(opts={}){ return new Promise((res,rej)=>{navigator.geolocation.getCurrentPosition(res,rej,opts);}); }

let lastAcceptedPoint = null;
function isJumpTooLarge(prev, curr, dtSeconds) {
  if (!prev) return false;
  // حساب المسافة بالكيلومتر
  const d = L.latLng(prev[0],prev[1]).distanceTo(L.latLng(curr[0],curr[1]))/1000;
  const speedKmh = (d / Math.max(1, dtSeconds) ) * 3600;
  // عتبة منطقية — 200 كم/س
  return speedKmh > 200;
}

/* ----- batch sending ----- */
async function sendBatchIfNeeded(force=false){
  if (pendingBatch.length === 0) return;
  if (awaitingServer && !force) return;
  if (pendingBatch.length < Math.min(10, BATCH_SIZE) && !force) return; // لا ترسل دفعات صغيرة جداً تلقائياً
  // إرسال
  awaitingServer = true;
  setButtonsState();
  const payload = {
    ride_id: rideId,
    points: pendingBatch
  };
  try {
    const res = await fetch(location.pathname + '?action=track_batch', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    // في حال نجاح، نزيل النقاط المرسلة من pending
    if (data.ok) {
      // نحذف عدد النقاط المحفوظة
      pendingBatch.splice(0, data.saved_points || pendingBatch.length);
    } else {
      console.warn('batch send response', data);
    }
  } catch(e){
    console.warn('batch send failed', e);
  } finally {
    awaitingServer = false;
    setButtonsState();
  }
}

/* ----- startWatch: الاستماع لنقاط GPS ----- */
function startWatch(){
  if (watchId !== null) return;
  let lastTs = null;
  watchId = navigator.geolocation.watchPosition((pos)=>{
    const now = new Date();
    document.getElementById('gpsStatus').textContent = `GPS: OK (accuracy ${pos.coords.accuracy}m)`;
    document.getElementById('lastUpdate').textContent = `آخر تحديث: ${now.toLocaleString()}`;
    const raw = [pos.coords.latitude, pos.coords.longitude];
    // init kalman if null
    if (!kalman) { kalman = new Kalman(raw[0], raw[1]); }
    const sm = kalman.update(raw[0], raw[1]); // [lat,lng]

    // منع القفزات غير المنطقية باعتماد فرق الزمن والسرعة
    const ts = Math.floor(Date.now()/1000);
    if (lastTs === null) lastTs = ts;
    const dt = Math.max(1, ts - lastTs);
    lastTs = ts;

    if (isJumpTooLarge(lastAcceptedPoint, sm, dt)) {
      // سجل telemetry للقفزة
      fetch(location.pathname + '?action=telemetry', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
        body: JSON.stringify({type:'jump_detected', message:'jump rejected', extra:{candidate:sm, dt}})
      }).catch(()=>{});
      return; // تجاهل النقطة
    }

    // قبول النقطة
    lastAcceptedPoint = sm;
    routePoints.push(sm);
    tracking.push({lat:sm[0],lng:sm[1],timestamp:new Date().toISOString()});
    pendingBatch.push({lat:sm[0],lng:sm[1],timestamp:new Date().toISOString()});
    // حافظ على طول محدود
    if (routePoints.length > MAX_TRACK) routePoints.shift();
    if (tracking.length > MAX_TRACK) tracking.shift();
    if (pendingBatch.length > 500) pendingBatch.splice(0, pendingBatch.length - 500);

    endCoords = sm;
    updatePolyline();
    updateUI(); // تحديث مباشر لحظة بلحظة

    // إرسال كل BATCH_SIZE أو وفق الـ interval
    if (pendingBatch.length >= BATCH_SIZE) {
      sendBatchIfNeeded(true);
    }
  }, (err)=>{
    console.warn('watchPosition error:',err);
    document.getElementById('gpsStatus').textContent = `GPS: Error (${err.code})`;
    // إرسال telemetry إلى السيرفر
    fetch(location.pathname + '?action=telemetry', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
      body: JSON.stringify({type:'watch_error', message:err.message, code:err.code, extra:{}})
    }).catch(()=>{});
  }, geoOptions);

  // مؤقت إرسال دفعات منتظم
  if (!batchTimer) {
    batchTimer = setInterval(()=>{ sendBatchIfNeeded(); }, BATCH_INTERVAL_MS);
  }
}

/* ----- إيقاف التتبع ----- */
function stopWatch(){
  if (watchId !== null) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
  if (batchTimer) {
    clearInterval(batchTimer); batchTimer = null;
  }
}

/* ----- واجهة disable/enable buttons أثناء الانتظار ----- */
function setButtonsState(){
  const startBtn = document.getElementById('startBtn');
  const endBtn = document.getElementById('endBtn');
  startBtn.disabled = awaitingServer || rideStarted;
  endBtn.disabled = awaitingServer || !rideStarted;
}

/* ----- بدء الرحلة ----- */
async function startRide(){
  if (rideStarted){ alert('⚠️ لديك رحلة نشطة بالفعل.'); return; }
  if(!navigator.geolocation){ alert('⚠️ جهازك لا يدعم GPS.'); return; }

  // تعطيل الأزرار أثناء التحقق
  awaitingServer = true; setButtonsState();

  try {
    // تحقق من السيرفر لو فيه رحلة نشطة
    const chk = await fetch('get_current_ride.php',{headers:{'Accept':'application/json'}});
    const cur = await chk.json();
    if(cur.active){
      alert('⚠️ لديك رحلة نشطة بالفعل. سيتم استرجاعها.');
      restoreRideIfAny();
      awaitingServer = false; setButtonsState();
      return;
    }

    const pos = await getPosOnce(geoOptions);
    startCoords=[pos.coords.latitude,pos.coords.longitude];
    startTime=new Date();

    // إرسال طلب بدء الرحلة
    const res=await fetch('start_ride.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
      body:JSON.stringify({lat:startCoords[0],lng:startCoords[1]})
    });
    const data=await res.json();
    if(data.error){ alert(data.error); awaitingServer = false; setButtonsState(); return; }

    rideId=data.ride_id;
    document.getElementById('uiShare').textContent=data.share_code||'—';
    rideStarted=true; secondsElapsed=0;
    routePoints.length=0; tracking.length=0; pendingBatch.length=0;
    lastAcceptedPoint = null; kalman = null;
    startMarker=L.marker(startCoords).addTo(map).bindPopup("🚀 بداية الرحلة").openPopup();
    routePoints.push(startCoords); updatePolyline();
    document.getElementById('livePanel').style.display='block';

    startWatch();
    timer=setInterval(()=>{secondsElapsed++; updateUI();},1000);

    saveRideState({ride_id:rideId,start_time:startTime.toISOString(),start_coords:startCoords,last_end:null,route_points:routePoints,tracking:tracking});
    document.getElementById('report').innerHTML="<p>✅ تم بدء الرحلة بنجاح.</p>";

    // تعطيل زر إنهاء الرحلة لمدة دقيقة
    document.getElementById('endBtn').disabled = true;
    document.getElementById('report').innerHTML += "<p>⏳ لا يمكنك إنهاء الرحلة قبل مرور دقيقة واحدة.</p>";
    setTimeout(()=>{ 
      if (rideStarted) document.getElementById('endBtn').disabled = false; 
      document.getElementById('report').innerHTML += "<p>✅ يمكنك الآن إنهاء الرحلة.</p>";
    }, 60000);

  }catch(e){ alert('خطأ أثناء بدء الرحلة: '+(e.message||e)); }
  awaitingServer = false; setButtonsState();
}

/* ----- إنهاء الرحلة ----- */
async function endRide(){
  if(!rideStarted||!startCoords||!startTime){ alert('⚠️ لا توجد رحلة نشطة.'); return; }
  // تعطيل الأزرار فورًا
  awaitingServer = true; setButtonsState();

  if(timer){clearInterval(timer); timer=null;}
  if(watchId!==null){navigator.geolocation.clearWatch(watchId); watchId=null;}
  rideStarted=false;

  try{
    const pos=await getPosOnce({enableHighAccuracy:true,timeout:30000,maximumAge:1000});
    endCoords=[pos.coords.latitude,pos.coords.longitude];
    // مرّ فلتر Kalman أخير
    if (!kalman) kalman = new Kalman(endCoords[0], endCoords[1]);
    endCoords = kalman.update(endCoords[0], endCoords[1]);
    routePoints.push(endCoords);
  }catch(_){
    if(!endCoords&&routePoints.length)endCoords=routePoints[routePoints.length-1];
    if(!endCoords)endCoords=startCoords;
    routePoints.push(endCoords);
  }
  updatePolyline();

  const endTime=new Date();
  const duration=Math.max(1,Math.round((endTime-startTime)/60000));
  const distance=Number(distanceKmFromRoute(routePoints).toFixed(3));

  endMarker=L.marker(endCoords).addTo(map).bindPopup("🏁 نهاية الرحلة").openPopup();

  // قبل الإرسال النهائي: تبسيط المسار (Douglas-Peucker)
  const simplified = simplifyDP(routePoints, 0.00008); // epsilon ~ 8e-5 ≈ بضعة أمتار حسب الإحداثيات
  // إرسال أي دفعات معلّقة أولاً
  await sendBatchIfNeeded(true);

  try{
    const res=await fetch('end_ride.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
      body:JSON.stringify({ride_id:rideId,lat:endCoords[0],lng:endCoords[1],duration,distance,tracking:simplified})
    });
    const data=await res.json();
    if(data.error){ alert(data.error); awaitingServer = false; setButtonsState(); return; }

    document.getElementById('report').innerHTML=`
      <h3>📄 تقرير الرحلة</h3>
      <p>المسافة: ${data.distance??distance} كم</p>
      <p>المدة: ${data.duration??duration} دقيقة</p>
      <p>التكلفة: ${data.total??'0.00'} جنيه</p>
      <p>رمز المشاركة: ${data.share_code??'—'}</p>
      <p>الحالة: ${data.status??'—'}</p>`;

    // ✅ عرض لوحة الدفع بعد التقرير
    showPaymentPanel(data.ride_id, parseFloat(data.total || 0));
  }catch(e){ alert('خطأ أثناء إنهاء الرحلة: '+(e.message||e)); }

  // تنظيف محلي
  clearRideState();
  document.getElementById('livePanel').style.display='none';
  startCoords=null; endCoords=null; startTime=null; secondsElapsed=0;
  routePoints.length=0; tracking.length=0; pendingBatch.length=0; rideId=null;
  awaitingServer = false; setButtonsState();
}

/* ----- Payment panel handlers (unchanged) ----- */
let lastRideId = null;
let lastTotal = 0;
function showPaymentPanel(rideIdArg, total){
  lastRideId = rideIdArg;
  lastTotal = total;
  document.getElementById('paymentPanel').style.display = 'block';
}
document.getElementById('payCashBtn').addEventListener('click', async ()=>{
  const res = await fetch('ride_pay_cash.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ ride_id: lastRideId })
  });
  const data = await res.json();
  alert(data.message || data.error || 'تم التنفيذ');
});
document.getElementById('verifyUserBtn').addEventListener('click', async ()=>{
  const toUserId = Number(document.getElementById('toUserId').value);
  const toUserEmail = document.getElementById('toUserEmail').value.trim();

  const res = await fetch('ride_verify_recipient.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ to_user_id: toUserId, to_user_email: toUserEmail })
  });
  const data = await res.json();

  const el = document.getElementById('verifyResult');
  if(data.error){
    el.textContent = data.error;
    document.getElementById('confirmTransferBtn').disabled = true;
  }else{
    el.textContent = `سيتم التحويل إلى: ${data.username} (ID: ${data.user_id}, Email: ${data.email})`;
    document.getElementById('confirmTransferBtn').disabled = false;
    el.dataset.userId = data.user_id;
    el.dataset.email = data.email;
  }
});
document.getElementById('confirmTransferBtn').addEventListener('click', async ()=>{
  const verifyEl = document.getElementById('verifyResult');
  const toUserId = Number(verifyEl.dataset.userId);
  const toUserEmail = verifyEl.dataset.email;

  const res = await fetch('ride_transfer_pay.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ ride_id: lastRideId, to_user_id: toUserId, to_user_email: toUserEmail })
  });
  const data = await res.json();
  alert(data.message || data.error || 'تم التنفيذ');
});

/* ----- استرجاع الرحلة عند التحميل ----- */
async function restoreRideIfAny(){
  const state=loadRideState();
  if(state&&state.ride_id){
    rideId=state.ride_id;
    startCoords=state.start_coords||null;
    endCoords=state.last_end||null;
    routePoints.length=0; tracking.length=0;
    if(Array.isArray(state.route_points))routePoints.push(...state.route_points);
    if(Array.isArray(state.tracking))tracking.push(...state.tracking);
    try{startTime=new Date(state.start_time);}catch(e){startTime=new Date();}
    if(startCoords){startMarker=L.marker(startCoords).addTo(map).bindPopup("🚀 بداية الرحلة");}
    updatePolyline();
    document.getElementById('livePanel').style.display='block';
    rideStarted=true;
    secondsElapsed=Math.max(0,Math.floor((Date.now()-startTime.getTime())/1000));
    updateUI();
    startWatch();
    if(!timer)timer=setInterval(()=>{secondsElapsed++; updateUI();},1000);
    return;
  }
  // لو مفيش حالة محلية، نسأل السيرفر
  try{
    const res=await fetch('get_current_ride.php',{headers:{'Accept':'application/json'}});
    const data=await res.json();
    if(data.active){
      rideId=data.id;
      startCoords=[parseFloat(data.start_lat),parseFloat(data.start_lng)];
      startTime=new Date(data.started_at);
      startMarker=L.marker(startCoords).addTo(map).bindPopup("🚀 بداية الرحلة");
      routePoints.push(startCoords); updatePolyline();
      document.getElementById('livePanel').style.display='block';
      rideStarted=true;
      secondsElapsed=Math.max(0,Math.floor((Date.now()-startTime.getTime())/1000));
      updateUI();
      saveRideState({ride_id:rideId,start_time:startTime.toISOString(),start_coords:startCoords,last_end:null,route_points:routePoints,tracking:tracking});
      startWatch();
      if(!timer)timer=setInterval(()=>{secondsElapsed++; updateUI();},1000);
    }else{ clearRideState(); }
  }catch(e){ console.warn('restore error:',e.message); }
}

/* ----- تهيئة الخريطة وربط الأزرار ----- */
function initMap(){
  map=L.map('map').setView([30.8,30.99],12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);
}

document.getElementById('startBtn').addEventListener('click',startRide);
document.getElementById('endBtn').addEventListener('click',endRide);
initMap();
restoreRideIfAny();
setButtonsState();
</script>

<?php include 'footer.php'; ?>
</body>
</html>
