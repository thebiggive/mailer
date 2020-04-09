<?php

declare(strict_types=1);

namespace Mailer\Application\Validator;

use Mailer\Application\Actions\ActionError;
use Mailer\Application\Actions\ActionPayload;
use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest as SendRequestModel;
use Twig;

class SendRequest
{
    private Config $config;
    private Twig\Environment $twig;
    private array $twigSettings;

    private string $reason;

    public function __construct(
        Config $configLoader,
        Twig\Environment $twig,
        array $twigSettings
    ) {
        $this->config = $configLoader;
        $this->twig = $twig;
        $this->twigSettings = $twigSettings;
    }

    public function validate(SendRequestModel $sendRequest, bool $full): bool
    {
        foreach (array_keys(get_class_vars(SendRequestModel::class)) as $property) {
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

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        array_map(static function ($subjectParam) use ($sendRequest) {
            if (!array_key_exists($subjectParam, $sendRequest->params)) {
                throw new \LogicException("Missing subject param '$subjectParam'");
            }
        }, $config->subjectParams);

        foreach ($config->requiredParams as $requiredParam) {
            // For required params, boolean false is fine. undefined and null and blank string are all prohibited.
            if (!isset($sendRequest->params[$requiredParam]) || $sendRequest->params[$requiredParam] === '') {
                $this->reason = "Missing required param '$requiredParam'";
                return false;
            }
        }

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

        return true;
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
