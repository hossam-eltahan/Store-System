<?php
/**
 * About & Help Page
 * صفحة حول النظام والمساعدة
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'حول النظام والمساعدة';

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.about-container {
    max-width: 900px;
    margin: 0 auto;
}

.hero-section {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed, #6d28d9);
    color: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 4s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.3; }
}

.hero-logo {
    font-size: 4em;
    margin-bottom: 15px;
}

.hero-title {
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 10px;
}

.hero-subtitle {
    font-size: 1.1em;
    opacity: 0.9;
}

.hero-version {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    margin-top: 15px;
    font-size: 0.9em;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.info-card-icon {
    font-size: 2.5em;
    margin-bottom: 15px;
}

.info-card-title {
    font-size: 1.3em;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 15px;
    border-bottom: 2px solid #8b5cf6;
    padding-bottom: 10px;
}

.info-card-content {
    color: #4b5563;
    line-height: 1.8;
}

.developer-section {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 2px solid #10b981;
}

.developer-name {
    font-size: 1.5em;
    font-weight: bold;
    color: #059669;
    margin-bottom: 10px;
}

.developer-title {
    color: #047857;
    font-weight: 500;
    margin-bottom: 15px;
}

.developer-bio {
    color: #374151;
    text-align: justify;
}

.contact-section {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 2px solid #3b82f6;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(59, 130, 246, 0.2);
}

.contact-item:last-child {
    border-bottom: none;
}

.contact-icon {
    font-size: 1.5em;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.contact-details {
    flex: 1;
}

.contact-label {
    font-size: 0.85em;
    color: #6b7280;
}

.contact-value {
    font-weight: bold;
    color: #1f2937;
}

.contact-value a {
    color: #2563eb;
    text-decoration: none;
}

.contact-value a:hover {
    text-decoration: underline;
}

.features-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.features-title {
    font-size: 1.3em;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 20px;
    text-align: center;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.feature-item {
    text-align: center;
    padding: 20px;
    background: #f9fafb;
    border-radius: 12px;
    transition: background 0.3s;
}

.feature-item:hover {
    background: #f3f4f6;
}

.feature-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.feature-text {
    font-weight: 500;
    color: #374151;
}

.social-links {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.social-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: white;
    border-radius: 10px;
    text-decoration: none;
    color: #374151;
    font-weight: 500;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.social-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.12);
}

.social-link.linkedin { border-top: 3px solid #0077b5; }
.social-link.github { border-top: 3px solid #333; }
.social-link.whatsapp { border-top: 3px solid #25d366; }

.footer-note {
    text-align: center;
    padding: 20px;
    color: #6b7280;
    font-size: 0.9em;
}

.copyright {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    margin-top: 20px;
}
</style>

<div class="container about-container">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-logo">🏠</div>
        <div class="hero-title">نظام إدارة محل أدوات منزلية</div>
        <div class="hero-subtitle">نظام متكامل لإدارة المحلات والمخازن - يعمل بدون إنترنت</div>
        <div class="hero-version">📦 الإصدار الأول - 2025</div>
    </div>
    
    <!-- Info Grid -->
    <div class="info-grid">
        <!-- Developer Section -->
        <div class="info-card developer-section">
            <div class="info-card-icon">👨‍💻</div>
            <div class="info-card-title">المطور</div>
            <div class="info-card-content">
                <div class="developer-name">حسام الطحان</div>
                <div class="developer-title">مطور نظم وتطبيقات ويب</div>
                <div class="developer-bio">
                    متخصص في بناء أنظمة إدارة المحلات والمخازن باستخدام PHP و MySQL.
                    لديه خبرة في تصميم أنظمة بسيطة وسهلة الاستخدام تناسب أصحاب الأعمال غير التقنيين،
                    مع التركيز على تحويل العمل الورقي إلى أنظمة رقمية دقيقة وموثوقة تعمل بدون إنترنت.
                </div>
            </div>
        </div>
        
        <!-- Contact Section -->
        <div class="info-card contact-section">
            <div class="info-card-icon">📞</div>
            <div class="info-card-title">تواصل معنا</div>
            <div class="info-card-content">
                <div class="contact-item">
                    <div class="contact-icon">📱</div>
                    <div class="contact-details">
                        <div class="contact-label">رقم الموبايل / واتساب</div>
                        <div class="contact-value">
                            <a href="tel:01271191616">01271191616</a>
                        </div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <div class="contact-details">
                        <div class="contact-label">البريد الإلكتروني</div>
                        <div class="contact-value">
                            <a href="mailto:hossameltahan2004@gmail.com">hossameltahan2004@gmail.com</a>
                        </div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">⏰</div>
                    <div class="contact-details">
                        <div class="contact-label">متوفر للتواصل</div>
                        <div class="contact-value" style="color: #10b981;">24/7 على مدار الساعة</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Features Section -->
    <div class="features-section">
        <div class="features-title">✨ مميزات النظام</div>
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📦</div>
                <div class="feature-text">إدارة المخزون</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🧾</div>
                <div class="feature-text">الفواتير</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-text">نظام الأقساط</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">👥</div>
                <div class="feature-text">إدارة العملاء</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🚚</div>
                <div class="feature-text">إدارة الموردين</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📊</div>
                <div class="feature-text">التقارير</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔄</div>
                <div class="feature-text">المرتجعات</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💾</div>
                <div class="feature-text">نسخ احتياطي</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📵</div>
                <div class="feature-text">بدون إنترنت</div>
            </div>
        </div>
    </div>
    
    <!-- Social Links -->
    <div class="features-section" style="text-align: center;">
        <div class="features-title">🔗 تابعني على</div>
        <div class="social-links">
            <a href="https://wa.me/201271191616" target="_blank" class="social-link whatsapp">
                <span style="font-size: 1.5em;">💬</span>
                <span>واتساب</span>
            </a>
            <a href="https://www.linkedin.com/in/hossam-eltahan-24528b253/" target="_blank" class="social-link linkedin">
                <span style="font-size: 1.5em;">💼</span>
                <span>LinkedIn</span>
            </a>
            <a href="https://github.com/hossam-eltahan" target="_blank" class="social-link github">
                <span style="font-size: 1.5em;">🐙</span>
                <span>GitHub</span>
            </a>
        </div>
    </div>
    
    <!-- Company -->
    <div class="copyright">
        <div style="font-size: 1.5em; margin-bottom: 5px;">🏢 كودتهالك</div>
        <div style="opacity: 0.8;">تطوير وبرمجة أنظمة إدارة الأعمال</div>
        <div style="margin-top: 10px; font-size: 0.9em; opacity: 0.7;">© 2025 جميع الحقوق محفوظة</div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
