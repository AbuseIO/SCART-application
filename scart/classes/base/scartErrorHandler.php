<?php namespace abuseio\scart\classes\base;

use October\Rain\Foundation\Exception\Handler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Exception;

/**
 * Class scartErrorHandler
 *
 * Own customer handler to handle customized responses
 * Not yet used, just in place
 *
 * @package abuseio\scart\classes
 */

class scartErrorHandler extends Handler {

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception) {

        /* The rest of this code is just the 'default' code from WinterCMS' error handler. */
        if (!class_exists('Event')) {
            return parent::render($request, $exception);
        }

        $statusCode = $this->getStatusCode($exception);
        $response = $this->callCustomHandlers($exception);

        if (!is_null($response)) {
            return Response::make($response, $statusCode);
        }

        if ($event = Event::fire('exception.beforeRender', [$exception, $statusCode, $request], true)) {
            return Response::make($event, $statusCode);
        }

        return parent::render($request, $exception);
    }


}
