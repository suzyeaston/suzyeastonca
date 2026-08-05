<?php
/*
Template Name: Resume
*/
get_header();

$resume_contact = array(
    array( 'label' => 'Vancouver, BC', 'url' => '' ),
    array( 'label' => '604-719-6121', 'url' => 'tel:+16047196121' ),
    array( 'label' => 'suzyeaston@icloud.com', 'url' => 'mailto:suzyeaston@icloud.com' ),
    array( 'label' => 'linkedin.com/in/suzyeaston', 'url' => 'https://www.linkedin.com/in/suzyeaston/' ),
    array( 'label' => 'suzyeaston.ca', 'url' => 'https://suzyeaston.ca/' ),
    array( 'label' => 'github.com/suzyeaston', 'url' => 'https://github.com/suzyeaston' ),
);

$toolkit_groups = array(
    array(
        'label' => 'Business analysis',
        'items' => 'Technical discovery, workflow and data-flow mapping, requirements clarification, stakeholder collaboration, technical documentation, test planning and production verification.',
    ),
    array(
        'label' => 'Integration and commerce',
        'items' => 'REST APIs, ecommerce, payments, POS, SaaS platforms, SSO/SCIM, cloud-connected systems, WordPress/PHP, structured web content and technical SEO familiarity.',
    ),
    array(
        'label' => 'Data and quality',
        'items' => 'SQL, KQL, API validation, data reconciliation, migration QA, Cypress, JavaScript/TypeScript, CI/CD, regression testing, New Relic, Rollbar and BrowserStack.',
    ),
    array(
        'label' => 'Cloud, security and AI',
        'items' => 'AWS, Azure/Entra ID, Microsoft 365, Google Workspace, Intune, Defender, Python, PowerShell, agentic workflows, MCP, model routing and governance-aware architecture.',
    ),
);

$alignment_items = array(
    'Agentic commerce: Designed multi-provider AI platform architecture and explored agentic, multimodal and MCP-based workflows for technical and service-delivery use cases.',
    'Ecommerce architecture: Tested connected workflows spanning APIs, payment gateways, checkout, taxation, fulfilment, POS, iPad/iOS and backend services in a multi-tenant winery SaaS platform.',
    'Integration literacy: Maps and troubleshoots APIs, data flows, SaaS dependencies, identity systems and cloud services; uses SQL, logs and observability tools to isolate failures across system boundaries.',
    'Requirements and delivery: Translates operational and customer problems into reproducible technical findings, validation plans, documentation and practical next steps for product, engineering, security, vendors and business stakeholders.',
);

$experience = array(
    array(
        'company' => 'Quercus IT Inc.',
        'role' => 'AI Strategist & Solutions Engineer',
        'dates' => 'May 2026 – Jul 2026',
        'location' => 'Canada',
        'bullets' => array(
            'Helped shape an Edmonton-based MSP\'s AI capabilities across strategy, architecture and hands-on solutions engineering.',
            'Designed internal AI platform architecture with multi-provider model routing, sensitivity-based model selection, role-aware workflows, governance controls and reusable components for future applications.',
            'Explored agentic, multimodal and MCP-based workflows; translated technical and operational use cases into prototypes, architecture, documentation and knowledge transfer.',
            'Worked across Azure architecture, cloud infrastructure, identity, security, privacy and responsible data handling while supporting GitHub-based development and stakeholder collaboration.',
        ),
    ),
    array(
        'company' => 'Alida',
        'role' => 'Senior IT Operations Analyst',
        'dates' => 'Jul 2024 – Apr 2026',
        'location' => 'Vancouver, BC',
        'bullets' => array(
            'Built Python, PowerShell and KQL automation for onboarding, reporting, alerts, security checks, self-service remediation and operational monitoring.',
            'Supported Entra ID, Active Directory, Microsoft 365, Google Workspace, SSO/SCIM, DNS, DHCP, Group Policy, SaaS administration and certificate workflows across a hybrid enterprise environment.',
            'Created Microsoft Defender Advanced Hunting and Slack alert workflows, working with security and operations stakeholders to define detection logic, investigation paths and remediation follow-up.',
            'Managed AWS-hosted Linux infrastructure using Systems Manager, IAM, SSH over VPN and VPC-connected tooling; investigated incidents, service health and platform dependencies.',
            'Worked with Microsoft and BambooHR-related APIs, adding checks and validation when third-party changes affected data flow or service behaviour.',
        ),
    ),
    array(
        'company' => 'WineDirect',
        'role' => 'Progressive Ecommerce, Support & QA Engineering Roles',
        'dates' => 'Feb 2021 – Jul 2024',
        'location' => 'Vancouver, BC',
        'subtitle' => 'Software QA Engineer · Level 2 Ecommerce Support · Ecommerce Client Success',
        'bullets' => array(
            'Promoted twice from client-facing winery ecommerce support to senior escalation work and then Software QA Engineering.',
            'Built and maintained Cypress and JavaScript automated test coverage for a multi-tenant ecommerce SaaS platform, integrating suites into CircleCI and GitHub Actions.',
            'Tested APIs, payment gateways, cart and checkout logic, taxation, fulfilment, POS, iPad and iOS workflows across Node.js, TypeScript, Ruby-based services and Xcode-related environments.',
            'Used AWS services including S3, Lambda, SNS/SQS, CloudWatch, Redshift and RDS-backed environments, plus SQL validation, for backend testing and production investigation.',
            'Detected dropped, duplicated and mismatched transactional records during migration QA, helping engineering isolate data issues before broader customer impact.',
            'Served as an escalation point for complex winery operations, translating customer and platform problems into reproducible technical findings and collaborating with product, engineering and a third-party backend team.',
        ),
    ),
);

$additional_experience = array(
    array(
        'company' => 'Western Forest Products / Ignite Technical Resources',
        'role' => 'Project Support Analyst',
        'dates' => 'Mar 2020 – Dec 2020',
        'location' => 'Vancouver, BC',
        'bullets' => array(
            'Supported enterprise modernization across a distributed B.C. forestry operation, including infrastructure upgrades, remote-work enablement, SharePoint/OneDrive migrations, cloud-connected systems and security hardening.',
            'Automated migration work with PowerShell and supported MFA, Intune, Active Directory, DNS, endpoint improvements and infrastructure cutovers.',
        ),
    ),
    array(
        'company' => 'Crisis Centre of BC',
        'role' => 'Level 3 IT Help Desk Coordinator',
        'dates' => 'Sep 2019 – Mar 2020',
        'location' => 'Vancouver, BC',
        'bullets' => array(
            'Assumed acting sysadmin responsibilities in a 24/7 mission-critical environment, maintaining Azure AD, VMware, Active Directory, DNS, firewalls, SSL certificates, endpoint policies, VPN/MFA, online chat services and web security.',
            'Helped lead SIP trunking and urgent remote-work transitions, coordinating vendors, laptop imaging and operational continuity under limited documentation.',
        ),
    ),
);

$earlier_roles = array(
    'ADP Canada — Paytech IBM Mainframe Payroll Support: national payroll operations, COBOL and batch troubleshooting, reporting, Workforce Now and modernization toward Java-based workflows.',
    'DHX Media — Help Desk & Development Support: Maya, AVID, rendering, asset synchronization, studio systems and MEL/Python-adjacent scripting support.',
    'The Comedy MIX — Box Office Supervisor / Operations: POS and EMV systems, live transaction and network issues, scheduling, contracts and front-of-house operations in a deadline-driven venue.',
);

$builds = array(
    array(
        'title' => 'Independent Technical Practice Development',
        'meta' => 'Vancouver, BC · Jul 2026 – Present',
        'body' => 'Building a client-facing practice around systems integration, workflow automation, applied AI, software quality, cloud and security-aware technical work while pursuing contract engagements.',
    ),
    array(
        'title' => 'suzyeaston.ca',
        'meta' => '',
        'body' => 'Custom WordPress, PHP and JavaScript portfolio with REST/API workflows, custom templates, structured content, technical SEO/canonical troubleshooting, AI-assisted prototypes and interactive web projects.',
    ),
    array(
        'title' => 'Lousy Outages',
        'meta' => '',
        'body' => 'Cloud and status-monitoring project with WordPress plugin architecture, REST endpoints, WP-Cron polling, provider APIs/RSS, caching, alert hooks and email-deliverability work.',
    ),
    array(
        'title' => 'Gastown Simulator',
        'meta' => '',
        'body' => 'Browser-based Vancouver prototype using civic/open data, route anchors, PowerShell and Node build scripts, tests, weather/time controls, minimap logic and iterative product design.',
    ),
    array(
        'title' => 'Track Analyzer / ASMR Lab / AI Film Club',
        'meta' => '',
        'body' => 'AI, audio and web prototypes for music feedback, procedural sound, synchronized audiovisual timelines and creative storytelling.',
    ),
);

$download_html = get_template_directory_uri() . '/assets/resume/suzy-easton-bsa-resume.html';
$download_pdf  = get_template_directory_uri() . '/assets/resume/Suzy_Easton_BSA_Resume.pdf';
?>

<main id="main-content" class="resume-page">
    <div class="resume-toolbar" aria-label="Resume actions">
        <span class="resume-toolbar__label">Suzy Easton // BSA Resume</span>
        <div class="resume-toolbar__actions">
            <a class="resume-btn resume-btn--primary" href="<?php echo esc_url( $download_pdf ); ?>" download="Suzy_Easton_BSA_Resume.pdf">Download PDF</a>
            <button type="button" class="resume-btn" data-resume-print>Print</button>
            <a class="resume-btn" href="<?php echo esc_url( $download_html ); ?>" download="Suzy_Easton_BSA_Resume.html">Download HTML</a>
            <a class="resume-btn" href="<?php echo esc_url( home_url( '/work-with-suzy/' ) ); ?>">Work With Suzy</a>
        </div>
    </div>

    <article class="resume-sheet" id="resume-document" aria-label="Suzy Easton resume">
        <aside class="resume-sidebar">
            <header>
                <h1 class="resume-sidebar__name">Suzy Easton</h1>
                <p class="resume-sidebar__title">Senior Business Systems Analyst</p>
                <p class="resume-sidebar__tags">Agentic AI + Integration // Ecommerce Systems // API + Data Flows // QA Automation</p>
            </header>

            <div class="resume-availability">Available immediately // Contract // Hybrid or remote</div>

            <nav class="resume-contact" aria-label="Contact information">
                <?php foreach ( $resume_contact as $item ) : ?>
                    <?php if ( $item['url'] ) : ?>
                        <a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html( $item['label'] ); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <section class="resume-sidebar-block" aria-labelledby="resume-toolkit-title">
                <h3 id="resume-toolkit-title">03 // Core Toolkit</h3>
                <?php foreach ( $toolkit_groups as $group ) : ?>
                    <div class="toolkit-group">
                        <strong><?php echo esc_html( $group['label'] ); ?></strong>
                        <p><?php echo esc_html( $group['items'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="resume-sidebar-block" aria-labelledby="resume-education-title">
                <h3 id="resume-education-title">07 // Education</h3>
                <ul>
                    <li>Douglas College — Music Technology Certificate</li>
                    <li>Columbia Academy — Digital / Analog Recording Arts Certificate</li>
                </ul>
                <p style="margin-top:0.65rem;">Former professional touring bassist; composed, produced and recorded with Steve Albini. Brings live-production composure, creative collaboration and a builder's instinct to technical work.</p>
            </section>
        </aside>

        <div class="resume-main">
            <section class="resume-section" aria-labelledby="resume-profile-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">01 //</span>
                    <h2 class="resume-section__title" id="resume-profile-title">Profile</h2>
                </div>
                <p>Senior technical systems professional with 12+ years across ecommerce SaaS, systems integration, QA automation, cloud operations, identity, security and applied AI. Combines deep technical literacy in APIs, backend data flows, payments and platform behaviour with clear documentation, cross-functional discovery and practical delivery. Recent work spans agentic and MCP-based AI workflows at Quercus, enterprise automation and security operations at Alida, and 3.5 years progressing through client success, escalation support and software QA at WineDirect.</p>
            </section>

            <section class="resume-section resume-alignment" aria-labelledby="resume-alignment-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">02 //</span>
                    <h2 class="resume-section__title" id="resume-alignment-title">Role Alignment</h2>
                </div>
                <ul>
                    <?php foreach ( $alignment_items as $item ) : ?>
                        <li><?php echo esc_html( $item ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="resume-section" aria-labelledby="resume-experience-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">04 //</span>
                    <h2 class="resume-section__title" id="resume-experience-title">Professional Experience</h2>
                </div>
                <?php foreach ( $experience as $role ) : ?>
                    <article class="resume-role">
                        <div class="resume-role__header">
                            <h3 class="resume-role__company"><?php echo esc_html( $role['company'] ); ?> <span style="font-weight:500;color:var(--resume-muted);">| <?php echo esc_html( $role['role'] ); ?></span></h3>
                            <span class="resume-role__meta"><?php echo esc_html( $role['dates'] ); ?></span>
                        </div>
                        <p class="resume-role__location"><?php echo esc_html( $role['location'] ); ?><?php echo ! empty( $role['subtitle'] ) ? ' · ' . esc_html( $role['subtitle'] ) : ''; ?></p>
                        <ul>
                            <?php foreach ( $role['bullets'] as $bullet ) : ?>
                                <li><?php echo esc_html( $bullet ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="resume-section" aria-labelledby="resume-additional-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">05 //</span>
                    <h2 class="resume-section__title" id="resume-additional-title">Additional Technical Experience</h2>
                </div>
                <?php foreach ( $additional_experience as $role ) : ?>
                    <article class="resume-role">
                        <div class="resume-role__header">
                            <h3 class="resume-role__company"><?php echo esc_html( $role['company'] ); ?> <span style="font-weight:500;color:var(--resume-muted);">| <?php echo esc_html( $role['role'] ); ?></span></h3>
                            <span class="resume-role__meta"><?php echo esc_html( $role['dates'] ); ?></span>
                        </div>
                        <p class="resume-role__location"><?php echo esc_html( $role['location'] ); ?></p>
                        <ul>
                            <?php foreach ( $role['bullets'] as $bullet ) : ?>
                                <li><?php echo esc_html( $bullet ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
                <article class="resume-role">
                    <h3 class="resume-role__company" style="font-size:0.82rem;">Earlier technical roles</h3>
                    <ul>
                        <?php foreach ( $earlier_roles as $role ) : ?>
                            <li><?php echo esc_html( $role ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </section>

            <section class="resume-section resume-builds" aria-labelledby="resume-builds-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">06 //</span>
                    <h2 class="resume-section__title" id="resume-builds-title">Independent Practice and Selected Builds</h2>
                </div>
                <ul>
                    <?php foreach ( $builds as $build ) : ?>
                        <li>
                            <strong><?php echo esc_html( $build['title'] ); ?></strong>
                            <?php if ( $build['meta'] ) : ?>
                                <span> — <?php echo esc_html( $build['meta'] ); ?></span>
                            <?php endif; ?>
                            <?php echo $build['meta'] ? '<br>' : ': '; ?>
                            <?php echo esc_html( $build['body'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <footer class="resume-footer">Suzy Easton // BSA + Integration // Vancouver, BC</footer>
        </div>
    </article>
</main>

<?php get_footer(); ?>
