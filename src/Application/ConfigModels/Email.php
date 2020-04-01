<?php

declare(strict_types=1);

namespace Mailer\Application\ConfigModels;

class Email
{
    /** @var string     Template file must be at "templates/{$templateKey}.html.twig" */
    public string $templateKey;

    public string $subject;

    /** @var string[]   Parameter values to be sprintf merged into `$subject`, in order. */
    public array $subjectParams = [];

    /**
     * @var string[]    Parameters which *must* be populated for emails to be sent. It is current possible to provide
     *                  additional, optional params and use them in templates on the fly, without defining them in
     *                  settings.
     */
    public array $requiredParams = [];

    /**
     * @param string    $templateKey    Template file must be at "templates/{$templateKey}.html.twig"
     * @param string    $subject        Subject with %s placeholders for any merge fields
     * @param array     $subjectParams  An array corresponding to those %s values, in order. Repeats allowed.
     *                                  Empty array for no placeholders.
     * @param array     $requiredParams The parameters we need populated to render the template successfully. If any are
     *                                  missing (boolean false is OK), emails will refuse to send. Note that you *can*
     *                                  currently merge in params without listing them here, but it's probably safer
     *                                  and clearer to avoid this.
     */
    public function __construct(string $templateKey, string $subject, array $subjectParams, array $requiredParams)
    {
        $this->templateKey = $templateKey;
        $this->subject = $subject;
        $this->subjectParams = $subjectParams;
        $this->requiredParams = $requiredParams;
    }
}
