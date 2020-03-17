<?php

namespace Dan\Shopify\Laravel\Support;

use Closure;
use Exception;
use Log;

/**
 * Class RateLimitedRequest
 *
 * @TODO Look at rate-limit headers in response and sleep more intelligently.
 */
class RateLimitedRequest
{
    /** @var mixed $response */
    private $response;

    /** @var Closure $request */
    private $request;

    /** @var int|null $attempts */
    private $attempts;

    /** @var string $nickname */
    private $nickname;

    /** @var array $errors */
    protected $errors = [];

    /**
     * RateLimitedRequest constructor.
     *
     * @param Closure $request
     * @param null $nickname
     * @param int|null $attempts If attempts null, than recurse infinite.
     */
    public function __construct(Closure $request, $nickname = null, $attempts = 3)
    {
        $this->request = $request;
        $this->nickname = $nickname ?: __CLASS__;
        $this->attempts = $attempts;
    }

    /**
     * @return $this
     */
    public function handle()
    {
        $attempts = $this->attempts;
        $request = $this->request;
        $nickname = $this->nickname;

        while (is_null($attempts) || $attempts > 0) {
            $this->errors = [];

            try {
                $this->response = $request();
                return $this;
            } catch (Exception $e) {
                // Store the last error only
                $this->errors = [$e];

                if (Util::exceptionStatusCode($e) == 429) {
                    $attempts--;
                    Log::channel(config('shopify.sync.log_channel'))->warning("cmd:rate_limit_exceeded:$nickname:attempts_left:$attempts");
                    sleep(config('app.rate_limit_stbf'));
                } else {
                    Log::debug('error:'.Util::exceptionStatusCode($e));
                    return $this;
                }
            }
        }

        return $this;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function response()
    {
        return $this->handle()->throwErrors()->getResponse();
    }

    /**
     * @param Closure $request
     * @param null $nickname
     * @param int $attempts
     * @return mixed
     */
    public static function respond(Closure $request, $nickname = null, $attempts = 3) {
        return (new static($request, $nickname, $attempts))->response();
    }

    /**
     * Whatever is returned from the closure (if anything)
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Throw errors, if any.
     *
     * @return $this
     * @throws Exception
     */
    public function throwErrors()
    {
        foreach ($this->getErrors() as $error) {
            if (is_object($error) && $error instanceof Exception) {
                throw $error;
            }
        }

        return $this;
    }
}
