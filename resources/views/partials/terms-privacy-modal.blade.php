<div class="legal-modal-overlay" id="legalModal" aria-hidden="true">
    <div class="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legalModalTitle">
        <div class="legal-modal-header">
            <div class="legal-tabs">
                <button type="button" class="legal-tab is-active" data-legal-tab="terms" id="legalModalTitle">Terms and Conditions</button>
                <button type="button" class="legal-tab" data-legal-tab="privacy">Privacy Policy</button>
            </div>
            <button type="button" class="legal-modal-close" data-legal-close aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="legal-modal-body">
            <div class="legal-panel is-active" data-legal-panel="terms">
                <p class="legal-doc-title">PROJECT M.A.N.G.R.O.V.E</p>
                <p class="legal-doc-subtitle">Terms and Conditions</p>
                <p class="legal-doc-meta">System: Project M.A.N.G.R.O.V.E &mdash; A GIS-Enabled Spatial Analysis Platform Integrating Orthomosaic Imagery and Soil Assessment for Mangrove Rehabilitation<br>Operated for: DENR-PENRO Davao Occidental</p>

                <h4>1. Acceptance of Terms</h4>
                <p>By logging into or using Project M.A.N.G.R.O.V.E ("the System"), whether through the web platform or the companion mobile application, you agree to be bound by these Terms and Conditions. If you do not agree, do not use the System. Continued use after any update to these Terms constitutes acceptance of the revised Terms.</p>

                <h4>2. Description of the System</h4>
                <p>Project M.A.N.G.R.O.V.E is a GIS-based spatial decision-support platform that assists in selecting soil sampling sites for mangrove rehabilitation. The System:</p>
                <ul>
                    <li>Lets users define an Area of Interest (AOI) and generate drone survey mission files;</li>
                    <li>Analyzes uploaded orthomosaic imagery using a pretrained image classification model to produce land cover and suitability scores;</li>
                    <li>Generates GPS-guided soil sampling points;</li>
                    <li>Pairs with an Android mobile application and an IoT soil probe to collect, geotag, and synchronize pH, electrical conductivity, moisture, and NPK readings;</li>
                    <li>Visualizes results through dashboards, heatmaps, and downloadable reports.</li>
                </ul>
                <p>The System is a decision-support tool. It does not perform drone flight execution, image stitching, or laboratory-grade chemical analysis, and it does not replace professional environmental or engineering judgment.</p>

                <h4>3. Eligibility and Authorized Users</h4>
                <p>The System is intended solely for authorized personnel of the Department of Environment and Natural Resources &mdash; Provincial Environment and Natural Resources Office (DENR-PENRO) of Davao Occidental who are involved in mangrove rehabilitation and coastal resource management activities. It is not intended for use by the general public, private individuals, or personnel of other agencies or offices. Access credentials are issued only to individuals authorized by DENR-PENRO.</p>

                <h4>4. Account Registration and Security</h4>
                <ul>
                    <li>You are responsible for maintaining the confidentiality of your login credentials and for all activity conducted under your account.</li>
                    <li>You must notify the system administrator / DENR-PENRO IT focal person immediately if you suspect unauthorized access to your account.</li>
                    <li>Accounts are non-transferable and may not be shared between personnel.</li>
                </ul>

                <h4>5. Acceptable Use</h4>
                <p>When using the System, you agree not to:</p>
                <ul>
                    <li>Use the System for any purpose outside official DENR-PENRO mangrove rehabilitation and environmental monitoring activities;</li>
                    <li>Upload imagery, data, or files you do not have the right to use;</li>
                    <li>Attempt to access accounts, data, or system areas you are not authorized to access;</li>
                    <li>Interfere with or disrupt the System's servers, networks, or the IoT soil probe hardware;</li>
                    <li>Reverse-engineer, copy, or redistribute the System's source code, trained models, or underlying architecture outside of authorized academic or agency purposes;</li>
                    <li>Introduce malware or attempt to probe, scan, or test the vulnerability of the System without authorization.</li>
                </ul>

                <h4>6. Intellectual Property</h4>
                <ul>
                    <li>The System's software, design, trained models, and documentation are the intellectual property of the developers, John Aaron M. Gicana and Godofredo D. Condinato, developed under the Institute of Computing, Engineering, and Technology, SPAMAST, and are the subject of a pending patent application. No part of the System may be reproduced or reused for commercial purposes without written permission.</li>
                    <li>Field data, soil readings, orthomosaic imagery, and reports generated through use of the System by DENR-PENRO personnel remain the property of DENR-PENRO and the Philippine government, in accordance with applicable government data ownership policy.</li>
                </ul>

                <h4>7. Accuracy and Limitations of System Output</h4>
                <p>You acknowledge and agree that:</p>
                <ul>
                    <li>Suitability scores and land cover classifications are generated by a pretrained model (ResNet18) fine-tuned on a limited dataset, and are provided as decision-support guidance only, not as definitive or laboratory-certified results;</li>
                    <li>Soil probe readings may be affected by sensor drift and require periodic recalibration; accuracy is not guaranteed under all field conditions;</li>
                    <li>GPS coordinates, waypoints, and mission files are estimates and should be verified against on-the-ground conditions before acting on them;</li>
                    <li>The System does not replace formal soil laboratory analysis, licensed environmental assessment, or professional engineering judgment for rehabilitation planning decisions.</li>
                </ul>

                <h4>8. Third-Party Tools and Services</h4>
                <p>The System is built using open-source frameworks and libraries (including but not limited to Node.js, Express.js, Flask, Leaflet.js, and PyTorch) and is hosted on a third-party cloud hosting provider. The developers are not responsible for outages, data loss, or security incidents caused solely by the failure of third-party infrastructure providers, though reasonable efforts will be made to select and maintain reliable providers.</p>

                <h4>9. Service Availability</h4>
                <p>The System is hosted on a basic cloud server subscription and is not guaranteed to be available at all times. Scheduled maintenance, updates, connectivity issues, or hosting limitations may cause temporary downtime. The developers will make reasonable efforts to notify users of planned maintenance where possible.</p>

                <h4>10. Mobile Application and Soil Probe Hardware</h4>
                <ul>
                    <li>The mobile application and paired IoT soil probe are intended for field use by trained DENR-PENRO technicians only.</li>
                    <li>Users are responsible for the reasonable care and handling of any soil probe hardware issued to them.</li>
                    <li>Offline-collected data is stored locally on the device until connectivity is restored; users are responsible for ensuring data is synchronized in a timely manner and for not deleting the app's local storage before synchronization is confirmed.</li>
                    <li>Mobile functionality may be limited by device compatibility, battery life, and network availability in remote field areas.</li>
                </ul>

                <h4>11. Suspension and Termination</h4>
                <p>DENR-PENRO or the System administrators may suspend or terminate a user's access at any time, including but not limited to cases of misuse, security concerns, or when a user is no longer part of DENR-PENRO Davao Occidental's rehabilitation operations.</p>

                <h4>12. Limitation of Liability</h4>
                <p>To the extent permitted by law, the developers and DENR-PENRO shall not be liable for indirect, incidental, or consequential damages arising from use of the System, including but not limited to decisions made based on suitability scores, soil readings, or generated mission files. The System is provided "as is" and "as available" as an academic capstone prototype, without warranties of any kind, express or implied, regarding fitness for a particular purpose beyond its stated scope.</p>

                <h4>13. Indemnification</h4>
                <p>Users agree to use the System responsibly and in accordance with these Terms. Misuse that causes harm to DENR-PENRO, the developers, or third parties may result in the responsible party being held accountable in accordance with applicable law and agency policy.</p>

                <h4>14. Governing Law</h4>
                <p>These Terms are governed by the laws of the Republic of the Philippines, including but not limited to the Data Privacy Act of 2012 (Republic Act No. 10173) where applicable to personal data processed by the System.</p>

                <h4>15. Changes to These Terms</h4>
                <p>These Terms may be updated from time to time to reflect changes to the System or applicable law. Users will be notified of material changes through the System or by direct notice to DENR-PENRO.</p>

                <h4>16. Contact Information</h4>
                <p>For questions about these Terms, contact DENR-PENRO Davao Occidental.</p>
            </div>

            <div class="legal-panel" data-legal-panel="privacy">
                <p class="legal-doc-title">PROJECT M.A.N.G.R.O.V.E</p>
                <p class="legal-doc-subtitle">Privacy Policy</p>

                <h4>1. Introduction and Scope</h4>
                <p>This Privacy Policy explains how Project M.A.N.G.R.O.V.E ("the System") collects, uses, stores, and protects information when used by authorized DENR-PENRO Davao Occidental personnel through the web platform and companion mobile application. It applies only to authorized personnel accounts and field data collected through official use of the System; it does not apply to any external or public-facing service, as the System is not open to the general public.</p>

                <h4>2. Information We Collect</h4>
                <p><strong>2.1 Account and Personnel Information</strong></p>
                <ul>
                    <li>Login credentials (username/employee ID and password)</li>
                    <li>Name and position/role within DENR-PENRO</li>
                    <li>Activity logs tied to user accounts (e.g., which user uploaded an orthomosaic, which technician recorded a soil reading)</li>
                </ul>
                <p><strong>2.2 Field and Environmental Data</strong></p>
                <ul>
                    <li>Area of Interest (AOI) boundaries and drone mission parameters</li>
                    <li>Uploaded orthomosaic imagery</li>
                    <li>Soil parameters collected via the IoT soil probe: pH, electrical conductivity/salinity, moisture content, and NPK levels</li>
                    <li>GPS coordinates and timestamps associated with soil sampling records</li>
                    <li>Generated suitability maps, heatmaps, and reports</li>
                </ul>
                <p><strong>2.3 Device and Technical Information</strong></p>
                <ul>
                    <li>Basic technical information from the mobile device used to access the app (e.g., device type, app version), to the extent needed for troubleshooting and offline synchronization</li>
                    <li>System logs generated during normal operation (e.g., login times, error logs) for security and maintenance purposes</li>
                </ul>

                <h4>3. Legal Basis for Processing</h4>
                <p>Personal data collected through the System is processed on the basis of:</p>
                <ul>
                    <li>Performance of official government functions by DENR-PENRO personnel in carrying out mangrove rehabilitation and environmental monitoring duties; and</li>
                    <li>Compliance with the Data Privacy Act of 2012 (RA 10173) and its Implementing Rules and Regulations, which govern the processing of personal data by Philippine government agencies.</li>
                </ul>

                <h4>4. How We Use Your Information</h4>
                <p>Information collected is used to:</p>
                <ul>
                    <li>Authenticate and manage user access to the System;</li>
                    <li>Attribute and organize field data (soil readings, imagery, reports) to the personnel and locations responsible for collecting them;</li>
                    <li>Generate suitability maps, dashboards, and reports to support DENR-PENRO's rehabilitation planning;</li>
                    <li>Maintain system security, troubleshoot issues, and improve System performance;</li>
                    <li>Support offline-to-online data synchronization for field operations.</li>
                </ul>
                <p>Field and environmental data (soil readings, GPS coordinates, imagery) is NOT used for any purpose beyond DENR-PENRO's mangrove rehabilitation and environmental monitoring operations, and is not sold or used for advertising of any kind.</p>

                <h4>5. Data Storage and Security</h4>
                <ul>
                    <li>Data is stored on the System's cloud hosting provider and, for field-collected data, temporarily on the mobile device pending synchronization.</li>
                    <li>Reasonable technical measures (e.g., authentication controls, access restricted to authorized accounts) are used to protect stored data.</li>
                    <li>As the System is hosted on a basic cloud subscription rather than enterprise-grade infrastructure, users should be aware this reflects the resource constraints of an academic capstone prototype, and additional security hardening is recommended before any full-scale deployment.</li>
                </ul>

                <h4>6. Data Sharing and Disclosure</h4>
                <ul>
                    <li>Data collected through the System is shared only within DENR-PENRO's internal reporting chain for official rehabilitation planning and monitoring purposes.</li>
                    <li>Data is not sold, rented, or shared with private third parties.</li>
                    <li>Data may be disclosed if required by Philippine law, court order, or lawful government audit/oversight process.</li>
                </ul>

                <h4>7. Data Retention</h4>
                <p>Personal account data is retained for as long as the individual remains authorized personnel of DENR-PENRO with active use of the System. Field and environmental data (soil records, imagery, reports) is retained in accordance with DENR-PENRO's records retention policy, as this data forms part of the agency's official environmental monitoring records.</p>

                <h4>8. Your Rights as a Data Subject</h4>
                <p>Under the Data Privacy Act of 2012 (RA 10173), personnel whose personal data is processed by the System have the right to:</p>
                <ul>
                    <li>Be informed that their personal data is being processed;</li>
                    <li>Access their personal data held in the System;</li>
                    <li>Request correction of inaccurate personal data;</li>
                    <li>Object to or request the erasure/blocking of personal data, subject to legal and operational requirements of DENR-PENRO's official records;</li>
                    <li>Lodge a complaint with the National Privacy Commission (NPC) if they believe their data has been mishandled.</li>
                </ul>

                <h4>9. Cookies and Analytics</h4>
                <p>The web platform may use basic session cookies necessary for keeping users logged in securely. The System does not use third-party advertising cookies or trackers.</p>

                <h4>10. Children's Privacy</h4>
                <p>The System is intended solely for authorized adult DENR-PENRO personnel and is not directed at, nor knowingly used by, children.</p>

                <h4>11. Changes to This Privacy Policy</h4>
                <p>This Privacy Policy may be updated from time to time to reflect changes to the System or applicable data privacy law. Material changes will be communicated to users through the System or through DENR-PENRO.</p>

                <h4>12. Contact Us / Data Protection Officer</h4>
                <p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, contact DENR-PENRO Davao Occidental.</p>
                <p>You may also file a complaint with the National Privacy Commission (NPC) at <a href="https://privacy.gov.ph" target="_blank" rel="noopener">privacy.gov.ph</a> if you believe your data privacy rights under RA 10173 have been violated.</p>
            </div>
        </div>
    </div>
</div>
