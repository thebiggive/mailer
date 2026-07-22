<?php

declare(strict_types=1);

namespace Mailer\Application\Handlers;

use JetBrains\PhpStorm\Pure;
use Mailer\Application\ResponseEmitter\ResponseEmitter;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpInternalServerErrorException;

final class ShutdownHandler
{
    #[Pure]
    public function __construct(
        private Request $request,
        private HttpErrorHandler $errorHandler,
        private bool $displayErrorDetails,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke()
    {
        $error = error_get_last();
        if ($error) {
            $errorFile = $error['file'];
            $errorLine = $error['line'];
            $errorMessage = $error['message'];
            $errorType = $error['type'];

            switch ($errorType) {
                // I think in practice we may never hit these first two as
                // they interrupt control flow so we don't get here, but included for completeness.
                case E_USER_ERROR:
                    $message = "FATAL USER ERROR: {$errorMessage}. ";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;

                case E_ERROR:
                    $message = "FATAL ERROR: {$errorMessage}. ";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;

                case E_USER_WARNING:
                    $message = "USER WARNING: {$errorMessage}";
                    break;

                case E_WARNING:
                    $message = "WARNING: {$errorMessage}";
                    break;

                case E_NOTICE:
                    $message = "NOTICE: {$errorMessage}";
                    break;

                case E_USER_NOTICE:
                    $message = "USER NOTICE: {$errorMessage}";
                    break;

                default:
                    $message = "ERROR: {$errorMessage}";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;
            }

            // Skip emitting a shutdown response from native warnings on non-dev envs, since events like Redis
            // connection failures cause these. These are already logged and if error-like output is emitted
            // alongside `/ping`'s more helpful output, its response body is left malformatted.
            if (
                (
                $errorType === E_WARNING &&
                str_contains($message, 'getaddrinfo failed: Name or service not known')
                )
            ) {
                return;
            }

            // maybe just a warning or PHP notice but we log it as an error since it could indicate important
            // info missing from the output, e.g. because we tried to read a field that didn't exist in data sent from
            // SF or FE. If this proves too noisy we might have to change this to just logging as warnings.
            $this->logger->error($errorMessage);

            if (\in_array($errorType, [\E_USER_WARNING, \E_WARNING, \E_NOTICE, \E_USER_NOTICE], true)) {
                // the output is probably better than nothing - if it wasn't, we would have had an error
                // so we return here to avoid issuing output that will break JSON parsing by the frontend.

                // A possible enhancement in future might be to include details of the error in a header or an additional
                // key added to the JSON response so that frontend can choose to display it.

                return;
            }

            $exception = new HttpInternalServerErrorException(
                $this->request,
                $this->displayErrorDetails ? $message : 'An error while processing your request. Please try again later.'
            );
            $response = $this->errorHandler->__invoke(
                $this->request,
                $exception,
                $this->displayErrorDetails,
                false, // Don't log via the less flexible built-in Slim fn; HttpErrorHandler does it via LoggerInterface
                false
            );

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
