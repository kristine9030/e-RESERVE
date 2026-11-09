<?php
/**
 * student/index.php
 *
 * The student-facing pages live at the project root (dashboard.php, documents.php,
 * ticket.php, tracking.php, profile.php) so that the
 * URLs stay short for the demo. This entry point simply forwards anyone who
 * opens /student/ to the dashboard.
 *
 * If you later prefer everything nested under /student/, move those files here
 * and update layouts/app_nav.php — no other change is required.
 */
require_once __DIR__ . '/../includes/config.php';

redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
