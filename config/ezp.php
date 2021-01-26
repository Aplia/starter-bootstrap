<?php
// Detect the ezp root unless a path is supplied
// The folder is either detected in the www-root or installed inside vendor/
if (!isset($_ENV['EZP_ROOT'])) {
    $foundKernel = false;
    $ezpRoot = null;
    if ($ezpRoot !== null) {
        putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = $ezpRoot);
        $foundKernel = true;
    }
    // If the kernel bootstrap file exists then use that to figure out the path
    if (!$foundKernel) {
        $wwwRoot = $_ENV['WWW_ROOT'];
        if (!$wwwRoot) {
            $wwwRoot = __DIR__ . "/../../../..";
        }
        if (file_exists($wwwRoot . '/vendor/ezsystems/ezlegacy/bootstrap_kernel.php')) {
            require_once $wwwRoot . '/vendor/ezsystems/ezlegacy/bootstrap_kernel.php';
            if (isset($_ENV['EZP_ROOT'])) {
                $foundKernel = true;
            }
        }
    }
    // Fall back to automagic guessing based on known files
    if (!$foundKernel) {
        if (file_exists($_ENV['WWW_ROOT'] . '/lib/ezutils') && file_exists($_ENV['WWW_ROOT'] . '/lib/version.php')) {
            putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['WWW_ROOT']));
            $foundKernel = true;
        }
        if (!$foundKernel) {
            foreach (array('aplia/ezpublish-legacy', 'ezsystems/ezpublish-legacy') as $ezpPath) {
                if (file_exists($_ENV['VENDOR_ROOT'] . $ezpPath)) {
                    putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['VENDOR_ROOT'] . $ezpPath));
                    $foundKernel = true;
                    break;
                }
            }
        }
    }
    if (!isset($_ENV['EZP_ROOT'])) {
        if (PHP_SAPI != 'cli') {
            header('Content-Type: text/html; charset=utf-8');
            header('503 Service Unavailable');
            if (!isset($GLOBALS['STARTER_BOOTSTRAP_DEV']) || !$GLOBALS['STARTER_BOOTSTRAP_DEV']) {
                echo "<h1>503 Service Unavailable</h1>";
            } else {
                echo "<h1>eZ publish root folder required</h1>\n";
                echo "<p>No root folder for eZ publish has been configured, a folder could not be detected either. Please set the \$_ENV['EZP_ROOT'] variable. See README.md for details.</p>\n";
            }
            exit();
        }
    }
    $ezpRoot = $_ENV['EZP_ROOT'];
}

return array(
    'app' => array(
        'bootstrap' => array(
            'classes' => array(
                'starter.ezp' => 'Aplia\Bootstrap\Ezp',
            ),
        ),
        'dump' => [
            'casters' => [
                // Dump virtual attributes on all persistent objects
                'eZPersistentObject' => ['Aplia\Bootstrap\VirtualAttributeCaster', 'castAttributes'],
            ],
            // Defines virtual attributes which are to be fetched when dumping/casting
            // Certain attributes are inexpensive to fetch, e.g. they are a simple function that returns
            // a new value from existing attributes.
            //
            // The format is:
            // '<class-name>' => Array of attribute names
            // e.g. ['eZContentObject' => ['data_map']]
            'virtualAttributes' => [
                'eZContentClass' => [
                    'ingroup_list',
                    'ingroup_id_list',
                    'group_list',
                    'name',
                    'nameList',
                    'description',
                    'descriptionList',
                    'languages',
                    'prioritized_languages',
                    'prioritized_languages_js_array',
                    'top_priority_language_locale',
                    'always_available_language',
                ],
                'eZContentClassAttribute' => [
                    'data_type',
                    'display_info',
                    'name',
                    'nameList',
                    'description',
                    'descriptionList',
                    'data_text_i18n',
                    'data_text_i18n_list',
                ],
                'eZContentObject' => [
                    'language_mask',
                    'initial_language_id',
                    'class_name',
                    'content_class',
                    'class_group_id_list',
                    'current_language',
                    'current_language_object',
                    'initial_language',
                    'initial_language_code',
                    'available_languages',
                    'language_codes',
                    'language_js_array',
                    'languages',
                    'all_languages',
                    'always_available',
                    'state_id_array',
                    'state_identifier_array',
                    'section_identifier',
                ],
                'eZContentObjectVersion' => [
                    'name',
                    'version_name',
                    'initial_language',
                    'language_list',
                ],
                'eZContentObjectAttribute' => [
                    'contentclass_attribute',
                    'contentclass_attribute_identifier',
                    'contentclass_attribute_name',
                    'has_content',
                    'has_http_value',
                    'language',
                    'is_a',
                    'display_info',
                ],
                'eZContentObjectTreeNode' => [
                    'name',
                    'sort_array',
                    'is_main',
                    'path_with_names',
                    'path_array',
                    'url',
                    'url_alias',
                    'class_identifier',
                    'class_name',
                    'hidden_invisible_string',
                    'hidden_status_string',
                    'classes_js_array',
                    'is_container',
                ],
                'eZContentLanguage' => [
                    'translation',
                    'locale_object',
                ],
                'eZUser' => [
                    'roles',
                    'role_id_list',
                    'limited_assignment_value_list',
                    'groups',
                    'is_logged_in',
                    'is_enabled',
                    'is_locked',
                    'last_visit',
                    'login_count',
                    'has_manage_locations',
                ]
            ],
            // Defines expensive virtual attributes which are to be fetched when dumping/casting
            // This is similar to 'virtualAttributes' but are considered very expensive to fetch
            // and are not performed by default.
            // However for debugging it may be useful to expand certain expensive attributes by
            // them to this array.
            // The format is:
            // '<class-name>' => Array of attribute names
            // e.g. ['eZContentObject' => ['data_map']]
            'expensiveAttributes' => [
                'eZContentClassAttribute' => [
                    'content',
                    'temporary_object_attribute',
                ],
                'eZContentObject' => [
                    'current',
                    'data_map',
                    'assigned_nodes',
                    'main_node',
                    'published_version',
                    'versions',
                    'author_array',
                ],
                'eZContentObjectVersion' => [
                    'creator',
                    'version_name',
                    'main_parent_node_id',
                    'contentobject_attributes',
                    'related_contentobject_array',
                    'reverse_related_object_list',
                    'parent_nodes',
                    'can_read',
                    'can_remove',
                    'data_map',
                    'node_assignments',
                    'contentobject',
                    'translation',
                    'translation_list',
                    'complete_translation_list',
                    'temp_main_node',
                ],
                'eZContentObjectAttribute' => [
                    // The 'content' attribute of an content-attribute can sometimes be expensive
                    'content',
                    'value',
                ],
                'eZContentObjectTreeNode' => [
                    // The 'content' attribute of an content-attribute can sometimes be expensive
                    'path',
                    'parent',
                    'object',
                    'data_map',
                    'can_read',
                    'can_pdf',
                    'can_create',
                    'can_edit',
                    'can_hide',
                    'can_remove',
                    'can_move',
                    'can_move_from',
                    'can_add_location',
                    'can_remove_location',
                    'can_view_embed',
                ],
                'eZUser' => [
                    'account_key',
                    'has_stored_login',
                ],
            ],
        ],
    ),
    'ezp' => array(
        'path' => $_ENV['EZP_ROOT'],
        // Controls how eZDebug will log
        // - psr - Logs are sent to configured psr log channel
        // - ezdebug - Logs are handled internally in eZDebug.
        // - disabled - All logging in eZDebug are disabled.
        'log_mode' => 'psr',
        // Maps ezpublish log levels to a log channel
        'loggers' => array(
            'strict' => 'ezpdebug.strict',
            'error' => 'ezpdebug.error',
            'warning' => 'ezpdebug.warning',
            'info' => 'ezpdebug.info',
            'debug' => 'ezpdebug.debug',
            'timing' => 'ezpdebug.timing',
        ),
    ),
    'log' => array(
        'handlers' => array(
            // This handler takes log records and forwards them to eZ debug
            'ezdebug' => array(
                'class' => '\\Aplia\\Bootstrap\\EzdebugHandler',
                'parameters' => array(),
            ),
            'var_log_error' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'level' => 'error',
                'parameters' => array(
                    'var/log/error.log'
                ),
            ),
            'var_log_deprecated' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'level' => 'error',
                'parameters' => array(
                    'var/log/deprecated.log'
                ),
            ),
            'var_log_warning' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'level' => 'warning',
                'parameters' => array(
                    'var/log/warning.log'
                ),
            ),
            'var_log_notice' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'level' => 'notice',
                'parameters' => array(
                    'var/log/notice.log'
                ),
            ),
            'var_log_debug' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'level' => 'debug',
                'parameters' => array(
                    'var/log/debug.log'
                ),
            ),
            // Combined log for all levels
            'var_log_ezp' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/ezp.log'
                ),
            ),
        ),
        // Add var/log/*.log files to log type 'file'
        'file_handlers' => array(
            'var_log_error' => 200,
            'var_log_deprecated' => 201,
            'var_log_warning' => 202,
            'var_log_notice' => 203,
            'var_log_debug' => 204,
            'var_log_ezp' => 205,
        ),
        'loggers' => array(
            'ezpdebug.strict' => array(
                'handlers' => array(
                    // Strict errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                    'console-err' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),
            'ezpdebug.error' => array(
                'handlers' => array(
                    // Errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                    'console-err' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),
            // The rest of the ezp channels are all placed in the same file
            'ezpdebug.warning' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                    'console' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),

            'ezpdebug.info' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                    'console' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),
            'ezpdebug.debug' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                    'console' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),
            'ezpdebug.timing' => array(
                'handlers' => array(
                    // Timing points are not generally interesting, goes nowhere by default
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
                'formatter' => 'line',
            ),
            // This receives logs from the error handler
            'phperror' => array(
                'handlers' => array(
                    // Enables ezdebug
                    // 'ezdebug' => 100,
                    // Log errors to error.log and ezp.log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                ),
                'formatter' => 'line',
            ),
            // Add base and site channels to the var/log files
            'base' => array(
                'handlers' => array(
                    // Strict errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                ),
                'formatter' => 'line',
            ),
            'site' => array(
                'handlers' => array(
                    // Strict errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                ),
                'formatter' => 'line',
            ),
        ),
    ),
);
