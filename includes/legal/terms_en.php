<?php
// includes/legal/terms_en.php - English version of terms.php (content only)
?>
<nav class="legal-toc" aria-label="Contents">
    <a href="#pranimi">1. Acceptance of terms</a>
    <a href="#sherbimi">2. The service</a>
    <a href="#llogaria">3. Your account</a>
    <a href="#perdorimi">4. Acceptable use</a>
    <a href="#permbajtja">5. Your content</a>
    <a href="#kodi">6. Source code</a>
    <a href="#disponueshmeria">7. Availability</a>
    <a href="#pergjegjesia">8. Limitation of liability</a>
    <a href="#mbyllja">9. Closing your account</a>
    <a href="#privatesia">10. Privacy</a>
    <a href="#ndryshimet">11. Changes</a>
    <a href="#ligji">12. Governing law</a>
    <a href="#kontakt">13. Contact</a>
</nav>

<article class="legal-content">

    <h2 id="pranimi">1. Acceptance of terms</h2>
    <p>By creating an account, signing in or using <?= e($legal_site) ?>, you accept these Terms of Service. If you do not agree with them, please do not use the service.</p>

    <h2 id="sherbimi">2. The service</h2>
    <p><?= e($legal_site) ?> is a free task management app run by <?= e($legal_owner) ?>: you can create tasks, categories, priorities and due dates, and mark them as done. We may change, extend or discontinue any feature at any time.</p>

    <h2 id="llogaria">3. Your account</h2>
    <ul>
        <li>You must be at least <?= (int)$legal_min_age ?> years old to create an account.</li>
        <li>You must provide accurate information and keep your account secure.</li>
        <li>Each account is tied to a single email. You must verify your email with the link we send you before signing in with email and password, and you must keep the email accessible, because that is where we send verification links, password reset links and security notices. Unverified accounts are deleted automatically after 3 days. If someone signs in with Google using an email that has an unverified registration, the account passes to that person and the previous password becomes invalid.</li>
        <li>You are responsible for every action taken with your account, including signing in with Google. When you link Google (your current password is required), the account email is replaced with your verified Google email and the old email is notified.</li>
        <li>Do not share your password with others. If you suspect unauthorized use, change your password or end your other sessions from Settings, and notify us immediately.</li>
    </ul>

    <h2 id="perdorimi">4. Acceptable use</h2>
    <p>You agree not to:</p>
    <ul>
        <li>use the service for unlawful or harmful purposes;</li>
        <li>try to access accounts or data that are not yours;</li>
        <li>disrupt or overload the server, or carry out attacks (e.g. brute force, SQL injection, XSS);</li>
        <li>bypass or attempt to bypass security protections, including rate limiting;</li>
        <li>use automated scripts to create accounts or extract data in bulk;</li>
        <li>upload content that contains viruses, is deceptive or infringes the rights of others.</li>
    </ul>

    <h2 id="permbajtja">5. Your content</h2>
    <p>The tasks and categories you create are yours. You only give us the permission needed to store them and display them to you inside the app. You are responsible for what you write and must not store unlawful content.</p>

    <h2 id="kodi">6. Source code</h2>
    <p>The app's source code is licensed under the GNU General Public License v3 (see the LICENSE file in the project). This license applies to the code, not to your content, which remains yours.</p>

    <h2 id="disponueshmeria">7. Availability</h2>
    <p>We try to keep the service running, but we do not guarantee that it will always be available or error-free. There may be interruptions for maintenance or for reasons beyond our control. For very important tasks, keep a copy somewhere else as well.</p>

    <h2 id="pergjegjesia">8. Limitation of liability</h2>
    <p>The service is provided "as is", without warranties of any kind. To the extent permitted by law, we are not liable for loss of data, missed deadlines or other consequential damages arising from use of the service.</p>

    <h2 id="mbyllja">9. Closing your account</h2>
    <p>You can stop using <?= e($legal_site) ?> at any time and delete your account yourself from Settings, in the "Delete account" section. Deletion removes the account, tasks and categories and cannot be undone. We may suspend or close an account that violates these terms or is used abusively.</p>

    <h2 id="privatesia">10. Privacy</h2>
    <p>How we handle your data is explained in the <a href="privacy.php">Privacy Policy</a>, which is part of these terms.</p>

    <h2 id="ndryshimet">11. Changes to the terms</h2>
    <p>We may update these terms from time to time. The date at the top of the page shows the latest version. If you keep using the service after changes, you are considered to have accepted them.</p>

    <h2 id="ligji">12. Governing law</h2>
    <p>These terms are governed by the laws of <?= e($legal_country_en ?? $legal_country) ?>. Disputes will first be sought to be resolved amicably, and if that fails, they are subject to the competent courts of that country.</p>

    <h2 id="kontakt">13. Contact</h2>
    <p>For questions about these terms: <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

</article>