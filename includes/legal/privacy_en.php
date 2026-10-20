<?php
// includes/legal/privacy_en.php - English version of privacy.php (content only)
?>
<nav class="legal-toc" aria-label="Contents">
    <a href="#hyrje">1. Introduction</a>
    <a href="#te-dhenat">2. Data we collect</a>
    <a href="#perdorimi">3. How we use it</a>
    <a href="#emailet">4. Emails we send you</a>
    <a href="#cookies">5. Cookies and sessions</a>
    <a href="#te-treta">6. Third-party services</a>
    <a href="#siguria">7. Security</a>
    <a href="#ruajtja">8. Retention and deletion</a>
    <a href="#te-drejtat">9. Your rights</a>
    <a href="#femijet">10. Minimum age</a>
    <a href="#ndryshimet">11. Changes</a>
    <a href="#kontakt">12. Contact</a>
</nav>

<article class="legal-content">

    <h2 id="hyrje">1. Introduction</h2>
    <p><?= e($legal_site) ?> is a free task management app run by <?= e($legal_owner) ?> (the data controller). This policy explains what data we collect when you use it, why we collect it, and what control you have over it.</p>
    <p>By creating an account or signing in, you confirm that you have read this policy. For the rules of use, please also see the <a href="terms.php">Terms of Service</a>.</p>

    <h2 id="te-dhenat">2. Data we collect</h2>
    <p><strong>Account data.</strong> When you sign up, we store:</p>
    <ul>
        <li>your username and email, and whether the email has been verified;</li>
        <li>your password, which is stored only in hashed (encrypted) form and cannot be read by us;</li>
        <li>how you sign in (email and password, Google, or both);</li>
        <li>the date the account was created.</li>
    </ul>

    <p><strong>Signing in with Google.</strong> If you choose "Continue with Google" or link Google from Settings, we receive from Google your email (and whether it is verified), your name and a unique Google account identifier. We never receive or see your Google password. Your name is only used to suggest an initial username. When you link Google to an existing account, your current password is required, the account email is replaced with your verified Google email, and the old email is notified (with the option to cancel the change). For accounts that sign in with Google only, we also use Google to re-verify your identity before sensitive actions (setting a password or deleting the account).</p>

    <p><strong>Your content.</strong> The tasks and categories you create (title, description, priority, due date, status and category color).</p>

    <p><strong>Technical data.</strong></p>
    <ul>
        <li>IP address, time of attempts and an account identifier (a hash of the email or the account number) for sign-in attempts, password confirmation in Settings and email requests (verification, password reset). We use them only to limit excessive attempts and protect accounts, never for profiling.</li>
        <li>A session cookie and a session version number stored on your account (see the Cookies and sessions section) and, for Google-only accounts, a temporary marker in the session (5 minutes) showing that you re-verified your identity with Google.</li>
        <li>Temporary tokens for email links (verification, password reset, email change, change cancellation). Only their hash is stored, not the token itself, and they expire within 1 to 24 hours depending on the type.</li>
    </ul>

    <h2 id="perdorimi">3. How we use your data</h2>
    <ul>
        <li>to create your account and sign you in;</li>
        <li>to store and display your tasks and categories, for you only;</li>
        <li>to verify your email and send you security emails (see the next section);</li>
        <li>to protect the service from abuse and unauthorized attempts;</li>
        <li>to fix bugs and improve the app.</li>
    </ul>
    <p>We do not sell your data, we do not show ads and we do not build marketing profiles. We may share data only with the technical providers that keep the app running (hosting, database and email service), or when required by law.</p>

    <h2 id="emailet">4. Emails we send you</h2>
    <p>We only send emails that are necessary for your account, never marketing emails:</p>
    <ul>
        <li>a verification email when you create an account;</li>
        <li>an email to set a new password, when you request it;</li>
        <li>a confirmation email when you change your email;</li>
        <li>a security notice when your account password or email changes (including when linking Google changes the email), with a link to cancel the change within 12 hours if it was not you;</li>
        <li>a notice when someone tries to sign up with your email that already has an account (you can ignore it if it was not you).</li>
    </ul>
    <p>Emails are sent through an SMTP service provider, which processes your email address only to deliver them.</p>

    <h2 id="cookies">5. Cookies and sessions</h2>
    <p>We use a session cookie that keeps you signed in and protects forms against CSRF attacks. It is essential for the site to work, and we do not use advertising or analytics cookies.</p>
    <ul>
        <li>The session cookie cannot be read by JavaScript (HttpOnly), is not sent by other sites on requests that change data (SameSite) and, when the site is opened over HTTPS, is only sent over HTTPS (Secure).</li>
        <li>It disappears when you close the browser or sign out.</li>
        <li>You can end your sessions on all other devices from Settings, in the "Sessions" section. Other sessions are also ended automatically when you change or reset your password.</li>
    </ul>

    <h2 id="te-treta">6. Third-party services</h2>
    <ul>
        <li><strong>Google Sign-In.</strong> When you sign in with Google, processing on Google's side is governed by their privacy policy.</li>
        <li><strong>Google Fonts.</strong> The site's fonts are loaded from Google's servers, so Google may receive your browser's IP address while loading them.</li>
        <li><strong>Email provider (SMTP).</strong> Used to send the emails described in section 4.</li>
    </ul>

    <h2 id="siguria">7. Security</h2>
    <p>We take reasonable measures to protect your data: passwords are stored hashed, database queries are parameterized, forms are protected against CSRF, sign-in attempts are rate-limited, and pages send security headers (e.g. Content-Security-Policy). No system is completely secure, so please use a strong password, never share it, and end your other sessions if you suspect unauthorized use.</p>

    <h2 id="ruajtja">8. Retention and deletion</h2>
    <p>We keep your data for as long as your account is active. You can delete your account yourself at any time from Settings, in the "Delete account" section. Deletion is immediate and irreversible: the account, all tasks and categories, and related tokens are removed. Technical data used for rate limiting (IP address, time and account identifier) is used only within the time window (about 15 minutes) and is deleted automatically after about 1 day. Accounts that do not verify their email within 3 days are deleted automatically, as are expired tokens.</p>

    <h2 id="te-drejtat">9. Your rights</h2>
    <p>Under applicable personal data protection law, you may have the right to:</p>
    <ul>
        <li>request access to the data we hold about you;</li>
        <li>correct inaccurate data (you can change your username, email and password yourself in Settings);</li>
        <li>request deletion of your data (you can do this yourself in Settings);</li>
        <li>request a copy of your data;</li>
        <li>object to or restrict its processing;</li>
        <li>lodge a complaint with the competent data protection authority.</li>
    </ul>
    <p>To exercise a right that you cannot exercise yourself in the app, write to us at <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

    <h2 id="femijet">10. Minimum age</h2>
    <p><?= e($legal_site) ?> is not intended for children under <?= (int)$legal_min_age ?> years old. If you believe a child under this age has created an account, contact us and we will delete it.</p>

    <h2 id="ndryshimet">11. Changes to this policy</h2>
    <p>We may update this policy from time to time. The date at the top of the page shows the latest version. If the changes are significant, we will let you know in the app.</p>

    <h2 id="kontakt">12. Contact</h2>
    <p>For any privacy question: <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

</article>