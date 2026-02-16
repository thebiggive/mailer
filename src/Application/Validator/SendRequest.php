<?php

declare(strict_types=1);

namespace Mailer\Application\Validator;

use JetBrains\PhpStorm\Pure;
use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest as SendRequestModel;
use Twig;

final class SendRequest
{
    private string $reason;

    #[Pure]
    public function __construct(
        private Config $config,
        private Twig\Environment $twig,
        private array $twigSettings
    ) {
    }

    public function validate(SendRequestModel $sendRequest, bool $full): bool
    {
        foreach (array_keys(get_class_vars(SendRequestModel::class)) as $property) {
            if ($property === 'sendingApplication') {
                // not required
                continue;
            }
            if (empty($sendRequest->{$property})) {
                $this->reason = 'Missing required data';
                return false;
            }
        }

        $config = $this->config->get($sendRequest->templateKey);
        if ($config === null) {
            $this->reason = "Template config for {$sendRequest->templateKey} not found";
            return false;
        }

        foreach ($config->requiredParams as $requiredParam) {
            // For required params, boolean false is fine. undefined and null and blank string are all prohibited.
            if (!isset($sendRequest->params[$requiredParam]) || $sendRequest->params[$requiredParam] === '') {
                $this->reason = "Missing required param '$requiredParam'";
                return false;
            }
        }

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        array_map(static function ($subjectParam) use ($sendRequest) {
            if (!array_key_exists($subjectParam, $sendRequest->params)) {
                throw new \LogicException("Missing subject param '$subjectParam'");
            }
        }, $config->subjectParams);

        if ($full) { // Perform a full Twig render to be sure it's going to work
            try {
                $this->twig->render("{$sendRequest->templateKey}.html.twig", $sendRequest->params);
            } catch (Twig\Error\LoaderError $ex) {
                $this->reason = "Template file for {$sendRequest->templateKey} not found";
                return false;
            } catch (Twig\Error\Error $ex) {
                $this->reason = 'Template render failed: ' . $ex->getMessage();
                return false;
            }
        } else { // Just check the template file exists
            if (!file_exists("{$this->twigSettings['templatePath']}/{$sendRequest->templateKey}.html.twig")) {
                $this->reason = "Template file for {$sendRequest->templateKey} not found";
                return false;
            }
        }

        // Make sure we can never have a consumer sending messages queued in a different environment.
        if ($sendRequest->env !== getenv('APP_ENV')) {
            $this->reason = "Cannot process messages for '{$sendRequest->env}' env";
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    #[Pure]
    public function getReason(): string
    {
        return $this->reason;
    }
}
