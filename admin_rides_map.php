<?php
session_start();
require_once 'config.php';
include 'header.php';

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

// جلب الرحلات النشطة
$query = "
SELECT r.id, r.user_id, r.start_lat, r.start_lng, r.share_code, r.created_at, u.name 
FROM rides r 
JOIN users u ON r.user_id = u.id 
WHERE r.status = 'started'
";
$result = $conn->query($query);
$active_rides = [];
while ($row = $result->fetch_assoc()) {
    $active_rides[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>خريطة الرحلات النشطة</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    #map { height: 600px; margin-top: 20px; }
  </style>
</head>
<body>
  <h2>🗺️ خريطة الرحلات النشطة للمستخدمين</h2>
  <p>عدد الرحلات النشطة: <strong><?php echo count($active_rides); ?></strong></p>

  <div id="map"></div>

  <script>
    const map = L.map('map').setView([30.8, 30.99], 12); // مركز طنطا كمثال
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap'
    }).addTo(map);

    const rides = <?php echo json_encode($active_rides); ?>;

    rides.forEach(ride => {
      const marker = L.marker([ride.start_lat, ride.start_lng]).addTo(map);
      marker.bindPopup(`
        <strong>🚶 المستخدم:</strong> ${ride.name}<br>
        <strong>🔗 رمز المشاركة:</strong> ${ride.share_code}<br>
        <strong>🕒 وقت البدء:</strong> ${ride.created_at}
      `);
    });
  </script>

  <br>
  <a href="admin_dashboard.php">⬅️ العودة للوحة التحكم</a>
  
<?php include 'footer.php'; ?>

    
    
    
</body>
</html>
