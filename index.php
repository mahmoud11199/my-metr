<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>TukMetr - نظام المحفظة للتكاتك</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background-color: #f4f6f9;
      direction: rtl;
      margin: 0;
      padding: 0;
      line-height: 1.8;
    }
    h2, h3 { color: #007bff; }
    section {
      background: #fff;
      padding: 20px;
      margin: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    ul, ol { margin-right: 20px; }
    .icon {
      font-size: 22px;
      margin-left: 8px;
    }
    .image-box {
      text-align: center;
      margin: 20px 0;
    }
    .image-box img {
      max-width: 100%;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    a {
      display: inline-block;
      margin: 10px 0;
      color: #007bff;
      text-decoration: none;
      font-weight: bold;
    }
    a:hover { text-decoration: underline; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    table, th, td {
      border: 1px solid #ccc;
    }
    th, td {
      padding: 10px;
      text-align: center;
    }
    th {
      background-color: #007bff;
      color: #fff;
    }
  </style>
</head>
<body>

<?php
session_start();

$page_title = "TukMetr - نظام المحفظة للتكاتك"; 
include 'header.php';

// التوجيه حسب حالة الجلسة
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        echo "<script>window.location.href = 'admin_dashboard.php';</script>";
        exit;
    } else {
        echo "<script>window.location.href = 'ride.php';</script>";
        exit;
    }
}
?>

  <h2>👋 مرحبًا بك في نظام <strong>TukMetr</strong></h2>
  <p>هذا النظام مخصص لإدارة رحلات <strong>التكاتك</strong> وحساب التكلفة بشكل ذكي.</p>

  <a href="login.html">🔐 تسجيل الدخول</a><br>
  <a href="register.html">📝 إنشاء حساب جديد</a>

  <!-- محتوى توضيحي للزوار -->
  <section>
    <h2>🛺 ما هو نظام TukMetr؟</h2>
    <p>
      <strong>TukMetr</strong> هو منصة ذكية لإدارة رحلات التكاتك والمعاملات المالية اليومية.  
      يتيح للسائقين والركاب تتبع الرحلات، حساب التكلفة تلقائيًا، وإدارة الرصيد بسهولة.
    </p>
    <div class="image-box">
      <img src="https://cdn-icons-png.flaticon.com/512/2972/2972185.png" alt="Tuk Tuk Icon">
    </div>

    <h3>🎯 أهداف المشروع</h3>
    <ul>
      <li>🚕 تنظيم رحلات التكاتك بشكل احترافي.</li>
      <li>💰 حساب التكلفة بناءً على الوقت والمسافة.</li>
      <li>📱 توفير محفظة إلكترونية للشحن والسحب.</li>
      <li>⚡ تجربة سلسة على الموبايل والمتصفح.</li>
    </ul>

    <h3>🧮 طريقة حساب التكلفة</h3>
    <p>
      يعتمد النظام على معادلة واضحة:
    </p>
    <p style="background:#eef; padding:10px; border-radius:6px;">
      <strong>التكلفة = سعر الفتح + (المسافة × سعر الكيلو) + (المدة × سعر الدقيقة)</strong>
    </p>

    <!-- جدول عملي -->
    <h3>📊 مثال عملي لحساب رحلة</h3>
    <table>
      <tr>
        <th>العنصر</th>
        <th>القيمة</th>
        <th>سعر الوحدة</th>
        <th>الإجمالي</th>
      </tr>
      <tr>
        <td>سعر الفتح</td>
        <td>—</td>
        <td>5 جنيه</td>
        <td>5 جنيه</td>
      </tr>
      <tr>
        <td>المسافة</td>
        <td>5 كم</td>
        <td>5 جنيه/كم</td>
        <td>25 جنيه</td>
      </tr>
      <tr>
        <td>المدة</td>
        <td>10 دقائق</td>
        <td>1 جنيه/دقيقة</td>
        <td>10 جنيه</td>
      </tr>
      <tr>
        <th colspan="3">الإجمالي</th>
        <th>40 جنيه</th>
      </tr>
    </table>

    <div class="image-box">
      <img src="https://cdn.pixabay.com/photo/2017/01/06/19/15/tuk-tuk-1951680_1280.jpg" alt="Tuk Tuk Photo">
    </div>

    <h3>👥 من يمكنه استخدام النظام؟</h3>
    <p>
      - <strong>الركاب:</strong> لمعرفة تكلفة الرحلة وإدارة الرصيد.  
      - <strong>السائقين:</strong> لتتبع الرحلات وحساب التكلفة بدقة.  
      
    </p>
  </section>

<?php
include 'footer.php';
?>

</body>
</html>
