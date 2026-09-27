<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = getenv('DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => false, 'dbport' => '', 'dbsocket' => ''];
$CFG->wwwroot = getenv('MOODLE_WWWROOT') ?: 'http://localhost:8080';
// TLS is terminated by the host's reverse proxy when wwwroot is https.
$CFG->sslproxy = str_starts_with($CFG->wwwroot, 'https://');
$CFG->dataroot = '/var/moodledata';
$CFG->directorypermissions = 02777;
require_once(__DIR__ . '/public/lib/setup.php');
