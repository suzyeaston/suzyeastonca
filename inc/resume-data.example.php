<?php
/**
 * Resume content template.
 *
 * Copy to inc/resume-data.php and fill in your details.
 * resume-data.php is gitignored and never committed.
 */

return array(
    'name' => 'Your Name',
    'title' => 'Your Title',
    'tags' => 'Skill // Skill // Skill // Skill',
    'availability' => 'Available immediately // Contract // Hybrid or remote',
    'profile' => 'Professional summary paragraph.',
    'education_note' => 'Education or creative signal paragraph.',
    'footer' => 'Your Name // Role // City',

    'contact' => array(
        array( 'label' => 'City, Region', 'url' => '' ),
        array( 'label' => 'phone@example.com', 'url' => 'mailto:phone@example.com' ),
    ),

    'toolkit_groups' => array(
        array(
            'label' => 'Category',
            'items' => 'Skills and tools for this category.',
        ),
    ),

    'alignment_items' => array(
        'Alignment bullet one.',
    ),

    'experience' => array(
        array(
            'company' => 'Company',
            'role' => 'Role',
            'dates' => 'Jan 2024 – Present',
            'location' => 'City',
            'subtitle' => '',
            'bullets' => array(
                'Achievement one.',
            ),
        ),
    ),

    'additional_experience' => array(),
    'earlier_roles' => array(),
    'builds' => array(),
    'education' => array(
        'School — Certificate or Program',
        'High School Name',
    ),
);
