<?php

declare(strict_types=1);

namespace Aplia\Bootstrap;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Aplia\Bootstrap\Base;

/**
 * This Monolog handler logs every message to a Sentry's server using the given
 * hub instance.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * Modified by Jan Borsodi <jborsodi@gmail.com>
 * Fixed to integrate with eZ publish, sends extra tags and records events.
 */
final class SentryHandler extends AbstractProcessingHandler
{
    /**
     * @var HubInterface
     */
    private $hub;
    /**
     * Controls how users are logged on sentry events.
     *
     * @var mixed
     */
    private $userLogging;
    /**
     * Salt string used for obfuscating user id.
     *
     * @var mixed
     */
    private $userSalt;

    /**
     * Constructor.
     *
     * @param HubInterface $hub    The hub to which errors are reported
     * @param int|string   $level  The minimum logging level at which this
     *                             handler will be triggered
     * @param bool         $bubble Whether the messages that are handled can
     *                             bubble up the stack or not
     */
    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->hub = $hub;

        parent::__construct($level, $bubble);

        $navigationEvents = Base::config('sentry.events.navigation', array());
        $userEvents = Base::config('sentry.events.user', array());
        $defaultEvents = Base::config('sentry.events.default', array());
        $this->userLogging = Base::config('sentry.user.logging', false);
        $this->userSalt = Base::config('sentry.user.salt', "");

        if ($navigationEvents) {
            // Record navigation events, will have eZURI as parameter
            foreach ($navigationEvents as $eventName) {
                \ezpEvent::getInstance()->attach($eventName,
                function ($uri) use ($eventName) {
                    \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
                        \Sentry\Breadcrumb::LEVEL_ERROR,
                        \Sentry\Breadcrumb::TYPE_NAVIGATION,
                        $eventName,
                        null,
                        array(
                            'uri' => $uri->uriString(true),
                        )
                    ));
                });
            }
        }

        // Record user events
        if ($userEvents) {
            foreach ($userEvents as $eventName) {
                \ezpEvent::getInstance()->attach($eventName,
                function ($uri) use ($eventName) {
                    \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
                        \Sentry\Breadcrumb::LEVEL_ERROR,
                        \Sentry\Breadcrumb::TYPE_USER,
                        $eventName,
                        null,
                        array(
                            'uri' => $uri,
                        )
                    ));
                });
            }
        }

        // Record normal events
        if ($defaultEvents) {
            foreach ($defaultEvents as $eventName) {
                \ezpEvent::getInstance()->attach($eventName,
                function () use ($eventName) {
                    \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
                        \Sentry\Breadcrumb::LEVEL_ERROR,
                        \Sentry\Breadcrumb::TYPE_DEFAULT,
                        $eventName,
                        null
                    ));
                });
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $payload = [
            'level' => self::getSeverityFromLevel($record['level']),
            'message' => $record['message'],
            'logger' => 'monolog.' . $record['channel'],
        ];

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $payload['exception'] = $record['context']['exception'];
        }

        $this->hub->withScope(function (Scope $scope) use ($record, $payload): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            if (isset($record['context']['extra']) && \is_array($record['context']['extra'])) {
                foreach ($record['context']['extra'] as $key => $value) {
                    $scope->setExtra((string) $key, $value);
                }
            }

            if (isset($record['context']['tags']) && \is_array($record['context']['tags'])) {
                foreach ($record['context']['tags'] as $key => $value) {
                    $scope->setTag($key, $value);
                }
            }

            if (isset($record['context']['fingerprint'])) {
                $scope->setExtra("fingerprint", $record['context']['fingerprint']);
            }

            // If the eZUser is currently loaded then use it to record current user
            if (class_exists("\\eZUser") && $this->userLogging) {
                $user = \eZUser::currentUser();
                if ($this->userLogging === 'username') {
                    $scope->setUser(array(
                        "username" => $user->attribute("login"),
                    ));
                } else if ($this->userLogging === 'email') {
                    $scope->setUser(array(
                        "username" => $user->attribute("login"),
                        "email" => $user->attribute("email"),
                    ));
                } else if ($this->userLogging === 'hash') {
                    $userId = 'hash:' . md5($this->userSalt . ':' . $user->attribute("login"));
                    $scope->setUser(array(
                        "id" => $userId,
                    ));
                }
            }
            if (class_exists("\\eZModule", false) && isset($GLOBALS['eZRequestedModule'])) {
                $module = $GLOBALS['eZRequestedModule'];
                if ($module->singleFunction()) {
                    $viewName = $module->Module["function"]["script"];
                    $scriptName = $module->Module["function"]["script"];
                } else {
                    $viewName = "";
                    if (isset($GLOBALS['eZRequestedModuleParams']['function_name'])) {
                        $viewName = $GLOBALS['eZRequestedModuleParams']["function_name"];
                    }
                    $scriptName = $module->Functions[$viewName]["script"];
                }
                $scope->setTag("controller", $module->attribute("path") . "/" . $module->uiComponentName() . "/$scriptName");
                $scope->setTag("ui_context", $module->uiContextName());
                $scope->setTag("route", $module->attribute("name") . "/" . $viewName);
                // eZSys::requestURI()
            }
            if (class_exists("\\eZSys", false)) {
                $scope->setTag("ezp.request_url", \eZSys::requestURI());
            }
            if (class_exists("\\eZSiteAccess", false)) {
                $siteAccess = \eZSiteAccess::current();
                if ($siteAccess) {
                    $scope->setTag("site_access", $siteAccess['name']);
                    $accessType = $siteAccess['type'];
                    if ($accessType === 1) {
                        $accessType = "default";
                    } else if ($accessType === 2) {
                        $accessType = "url";
                    } else if ($accessType === 3) {
                        $accessType = "port";
                    } else if ($accessType === 4) {
                        $accessType = "host";
                    }
                    $scope->setTag("site_access.type", (string)$accessType);
                }
            }
            if (class_exists("\\eZContentLanguage")) {
                $language = \eZContentLanguage::topPriorityLanguage();
                if ($language) {
                    $scope->setTag("page_locale", $language->attribute("locale"));
                }
            }
            if (class_exists("\\eZPublishSDK")) {
                $version = \eZPublishSDK::version();
                $scope->setTag("ezp.version", $version);
            }

            $this->hub->captureEvent($payload);
        });
    }

    /**
     * Translates the Monolog level into the Sentry severity.
     *
     * @param int $level The Monolog log level
     */
    private static function getSeverityFromLevel(int $level): Severity
    {
        switch ($level) {
            case Logger::DEBUG:
                return Severity::debug();
            case Logger::INFO:
            case Logger::NOTICE:
                return Severity::info();
            case Logger::WARNING:
                return Severity::warning();
            case Logger::ERROR:
                return Severity::error();
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                return Severity::fatal();
            default:
                return Severity::info();
        }
    }
}
