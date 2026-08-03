<?php
/**
 * Card-o-Bot Privacy Policy
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/console.php';

$basePath = get_base_path();

console_start('Privacy Policy - Card-o-Bot');
?>
                <article class="legal-console">
                    <nav class="legal-console__nav" aria-label="Legal navigation">
                        <a href="<?php echo $basePath; ?>/login.php">Login</a>
                        <span aria-hidden="true">|</span>
                        <a href="<?php echo $basePath; ?>/terms.php">Terms</a>
                    </nav>

                    <header class="legal-console__header">
                        <p class="legal-console__eyebrow">Card-o-Bot Legal</p>
                        <h1>Privacy Policy</h1>
                        <p class="legal-console__updated">Effective: April 29, 2026<br>Last updated: April 29, 2026</p>
                    </header>

                    <section class="legal-console__summary" aria-label="Short privacy summary">
                        <h2>Short Version</h2>
                        <p>Card-o-Bot needs account, prompt, generated-card, and technical data to run the app. We may feature your cards in Card-o-Bot marketing, galleries, and published card packs as permitted by our <a href="<?php echo $basePath; ?>/terms.php">Terms of Service</a>. We do not sell your personal information. We use service providers such as OpenAI and Google only to provide, secure, and improve Card-o-Bot. Google sign-in only gives us your name, email, profile picture, and Google ID; optional Drive or Photos export only lets Card-o-Bot save files it creates, not read anything else in your account.</p>
                    </section>

                    <section>
                        <h2>1. Who We Are</h2>
                        <p>Card-o-Bot is an AI-assisted card creation application operated by Christian Herbie Clarke, doing business as Herbie Creative ("Card-o-Bot," "we," "us," or "our"). This Privacy Policy explains how we collect, use, store, disclose, and protect information when you use Card-o-Bot at <strong>cardobot.com</strong>, <strong>herbiecreative.com/cardobot</strong>, or related Card-o-Bot pages and APIs.</p>
                        <p>This policy supplements the Herbie Creative site-wide Privacy Policy. If this policy and the site-wide policy conflict for Card-o-Bot, this Card-o-Bot policy controls.</p>
                    </section>

                    <section>
                        <h2>2. Information We Collect</h2>
                        <h3>Account Information</h3>
                        <p>When you create or use an account, we may collect your username, password hash, email address, name, Google account identifier, profile image, authentication method, account creation date, and last-login date.</p>

                        <h3>Card and Creative Data</h3>
                        <p>We collect the prompts, chat messages, selections, card names, card text, card type, card attributes, generated artwork, saved cards, card metadata, and related creation history you submit or create in Card-o-Bot.</p>

                        <h3>Technical and Usage Data</h3>
                        <p>We may collect IP address, browser type, device information, operating system, referring URL, timestamps, session data, pages or API routes used, error logs, and security logs. This helps us keep the app working and protect it from abuse.</p>

                        <h3>Information From Google Sign-In</h3>
                        <p>When you sign in with Google, Card-o-Bot requests the <code>openid</code>, <code>email</code>, and <code>profile</code> scopes. Google provides your Google account ID, primary email address, name, and profile picture URL. We store the Google account ID, email, name, and profile picture reference on your Card-o-Bot account so we can authenticate you, merge accounts, and display your profile. We do not request or receive your Gmail, contacts, calendar, Drive files, or other Google data from these scopes.</p>

                        <h3>Optional Google Drive and Google Photos Export</h3>
                        <p>If you enable a Card-o-Bot feature that saves a card to your own Google Drive or Google Photos, Card-o-Bot will ask Google for additional permissions:</p>
                        <ul>
                            <li><code>https://www.googleapis.com/auth/drive.file</code> — lets Card-o-Bot create and manage <em>only</em> the card files it saves for you in Drive. Card-o-Bot cannot list, read, modify, or delete other files in your Drive.</li>
                            <li><code>https://www.googleapis.com/auth/photoslibrary.appendonly</code> — lets Card-o-Bot add card images to your Google Photos library. Card-o-Bot cannot view, list, or modify your existing photos.</li>
                        </ul>
                        <p>These exports only run when you ask for them. Card-o-Bot uses and transfers information received from Google APIs in accordance with the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements. We do not use Google user data to train generalized or third-party AI models and do not transfer Google user data to third parties except as required to operate the user-requested export or as required by law.</p>

                        <h3>Information From AI Providers</h3>
                        <p>When AI services generate or process your cards, those providers receive the prompts, chat messages, and related content needed to return the requested output. Outputs they return are stored on your Card-o-Bot account.</p>
                    </section>

                    <section>
                        <h2>3. How We Use Information</h2>
                        <ul>
                            <li>To create, authenticate, secure, and manage user accounts.</li>
                            <li>To generate chat responses, card concepts, card text, and card artwork.</li>
                            <li>To save, retrieve, display, edit, and organize your Card-o-Bot collection.</li>
                            <li>To export cards to your Google Drive or Google Photos when you request it.</li>
                            <li>To feature cards in Card-o-Bot marketing, social media, galleries, showcases, curated packs, compilations, merchandise, case studies, and other promotional or published materials, under the content license in our <a href="<?php echo $basePath; ?>/terms.php">Terms of Service</a>.</li>
                            <li>To troubleshoot bugs, monitor performance, prevent fraud, and protect the app.</li>
                            <li>To communicate with you about account, security, support, and service matters.</li>
                            <li>To comply with legal obligations and enforce our Terms of Service.</li>
                        </ul>
                        <p>We do not sell your personal information. We do not use your Card-o-Bot prompts, saved cards, or generated images for third-party behavioral advertising, and we do not use Google user data to train generalized or third-party AI models.</p>
                    </section>

                    <section>
                        <h2>4. AI Processing</h2>
                        <p>Card-o-Bot uses third-party AI providers, including OpenAI, to process prompts and generate text and images. When you ask Card-o-Bot to create or revise content, the information needed to complete that request may be sent to those providers.</p>
                        <p>You should not submit sensitive personal information, confidential business information, health information, financial information, government ID numbers, passwords, private keys, or anything you do not have the right to use. AI systems may produce inaccurate, unexpected, or similar-looking content, and you are responsible for reviewing outputs before using them.</p>
                    </section>

                    <section>
                        <h2>5. Cookies and Sessions</h2>
                        <p>Card-o-Bot uses essential cookies and PHP sessions to keep you signed in, protect your session, remember account state, and operate the app. These cookies are required for logged-in features. We may also rely on Google sign-in scripts or related Google cookies when Google authentication is enabled.</p>
                    </section>

                    <section>
                        <h2>6. How We Share Information</h2>
                        <p>We share information only when needed to operate Card-o-Bot, comply with law, protect rights and safety, feature content consistent with our Terms of Service, or follow your direction. Service providers and recipients may include:</p>
                        <ul>
                            <li><strong>OpenAI:</strong> AI text and image generation.</li>
                            <li><strong>Google:</strong> OAuth sign-in, account authentication, and — only when you use the feature — Google Drive and Google Photos export.</li>
                            <li><strong>Bluehost or other hosting providers:</strong> Web hosting, databases, logs, storage, and server infrastructure.</li>
                            <li><strong>CDN, email, analytics, or asset providers:</strong> Delivery of fonts, icons, scripts, static assets, transactional email, or aggregate performance measurement if used by the page.</li>
                            <li><strong>Marketing, editorial, print, or merchandise partners:</strong> To produce, distribute, print, or promote card packs, merchandise, promotional materials, or published showcases featuring Card-o-Bot cards, consistent with the content license in our Terms of Service. These partners receive card images, card text, and sometimes a creator handle, but not your email, password, or other private account data.</li>
                            <li><strong>Successors:</strong> In connection with a merger, acquisition, reorganization, sale of assets, or similar transaction involving Card-o-Bot or Herbie Creative.</li>
                        </ul>
                        <p>We may disclose information if required by law, subpoena, court order, lawful government request, or when we believe disclosure is necessary to protect users, Card-o-Bot, Herbie Creative, or others.</p>
                    </section>

                    <section>
                        <h2>7. Your Content, Visibility, and Promotional Use</h2>
                        <p>Your saved cards and generated images are associated with your account and are stored on Card-o-Bot servers so you can view and manage them. Card-o-Bot is designed for personal account-based storage, but no online system is perfectly private or secure.</p>
                        <p>By using Card-o-Bot, you also grant Card-o-Bot and Herbie Creative the broad content license described in Section 6 of the <a href="<?php echo $basePath; ?>/terms.php">Terms of Service</a>. That license allows us to use, display, publish, remix, and distribute your cards in Card-o-Bot or Herbie Creative marketing, galleries, card packs, compilations, merchandise, and other promotional or commercial materials, with or without a public credit to you, at our discretion. Featured cards may appear with a username, display name, generated handle, or no identifying label.</p>
                        <p>Please do not create or store content that you would be harmed by losing, disclosing, having reviewed for safety, moderation, debugging, or legal compliance, <em>or having featured publicly by Card-o-Bot</em>. If you do not want a specific card to be usable in any medium, do not create or save it in Card-o-Bot.</p>
                    </section>

                    <section>
                        <h2>8. Data Retention</h2>
                        <ul>
                            <li><strong>Account records:</strong> Kept while your account is active and for a reasonable period afterward for security, backup, and legal purposes.</li>
                            <li><strong>Saved cards and generated images:</strong> Kept while your account or collection remains active, unless deleted by you or removed under our policies.</li>
                            <li><strong>Prompts and chat-related records:</strong> Kept as needed to provide the app, debug issues, preserve saved cards, and protect against abuse.</li>
                            <li><strong>Logs and technical data:</strong> Kept on a rolling or as-needed basis for security, diagnostics, and hosting operations.</li>
                        </ul>
                        <p>Backups, caches, logs, or records required for security or legal reasons may persist for a limited time after deletion from active systems.</p>
                    </section>

                    <section>
                        <h2>9. Security</h2>
                        <p>We use reasonable technical and organizational safeguards, including HTTPS, hashed passwords, server-side sessions, restricted configuration, and database access controls. No method of transmission or storage is perfectly secure, and we cannot guarantee absolute security.</p>
                    </section>

                    <section>
                        <h2>10. Children</h2>
                        <p>Card-o-Bot is not directed to children under 13. Users under 18 should use Card-o-Bot only with permission from a parent or legal guardian. Children under 13 may not create accounts or submit personal information. If we learn that we collected personal information from a child under 13 without appropriate consent, we will delete it.</p>
                    </section>

                    <section>
                        <h2>11. Your Choices and Rights</h2>
                        <p>You may request access, correction, deletion, or export of personal information associated with your account, subject to identity verification, technical limits, backup retention, safety needs, and legal requirements. Depending on your location, you may have additional rights under privacy laws such as CCPA/CPRA, GDPR, or similar laws.</p>
                        <p>You can revoke Card-o-Bot's access to your Google account (including any Drive or Photos permissions) at any time from your <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">Google Account permissions page</a>. Revoking will stop further exports or Google sign-in, but will not automatically delete files already saved to your Drive or Photos or cards stored in Card-o-Bot.</p>
                        <p>Deleting your account or a specific card will remove it from the active Card-o-Bot app and, over time, from our backups, subject to retention in Section 8. Deletion does <strong>not</strong> require us to recall, unpublish, or destroy card packs, marketing materials, merchandise, or promotional content we already published or distributed under the content license in Section 6 of the Terms of Service, though we will make reasonable efforts to stop new promotional uses of a deleted card where practical.</p>
                    </section>

                    <section>
                        <h2>12. International Users</h2>
                        <p>Card-o-Bot is operated from the United States. If you use Card-o-Bot from outside the United States, your information may be transferred to, stored in, and processed in the United States and other locations where our service providers operate.</p>
                    </section>

                    <section>
                        <h2>13. Changes</h2>
                        <p>We may update this Privacy Policy as Card-o-Bot changes. When we do, we will update the "Last updated" date. Material changes may also be announced through the app, website, or email when appropriate.</p>
                    </section>

                    <section>
                        <h2>14. Contact</h2>
                        <p>For privacy questions or requests, contact:</p>
                        <p><strong>Christian Herbie Clarke</strong><br>Herbie Creative / Card-o-Bot<br><a href="mailto:christian@herbiecreative.com">christian@herbiecreative.com</a></p>
                    </section>

                    <nav class="legal-console__footer" aria-label="Legal footer">
                        <a href="<?php echo $basePath; ?>/terms.php">Terms of Service</a>
                        <span aria-hidden="true">|</span>
                        <a href="<?php echo $basePath; ?>/login.php">Back to login</a>
                    </nav>
                </article>
<?php
console_end();
?>
