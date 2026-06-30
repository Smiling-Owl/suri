<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'report/suri:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ],
    ],
    'report/suri:viewall'   => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ],
    ],
];