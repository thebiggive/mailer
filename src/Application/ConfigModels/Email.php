<?php

declare(strict_types=1);

namespace Mailer\Application\ConfigModels;

use JetBrains\PhpStorm\Pure;

final class Email
{
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
    #[Pure]
    public function __construct(
        public string $templateKey,
        public string $subject,
        public array $subjectParams,
        public array $requiredParams
    ) {
    }

    #[Pure]
    public static function fromConfigArray(array $configArray): Email
    {
        return new Email(
            $configArray['templateKey'],
            $configArray['subject'],
            $configArray['subjectParams'],
            $configArray['requiredParams'],
        );
    }
}
