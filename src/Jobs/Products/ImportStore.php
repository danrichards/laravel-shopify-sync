<?php

namespace Dan\Shopify\Laravel\Jobs\Products;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Events\Stores\UninstallSuggested;
use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\Util;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Queue\MaxAttemptsExceededException;

/**
 * Class ImportStore
 */
class ImportStore extends AbstractStoreJob
{
    /** @var int $tries */
    public $tries = 3;

    /** @var Store $store */
    protected $store;

    /** @var array $params */
    protected $params;

    /** @var bool $dryrun */
    protected $dryrun;

    /**
     * ImportStore constructor.
     *
     * @param Store $store
     * @param string $connection
     * @param array $params
     * @param bool $dryrun
     */
    public function __construct(Store $store, array $params = ['created_at_min' => 'last'], $connection = 'sync', $dryrun = false)
    {
        parent::__construct($store);

        $this->params = $params;
        $this->connection = $connection;
        $this->dryrun = $dryrun;
    }

    /**
     * @return $this
     */
    public function handle()
    {
        ini_set('max_execution_time', config('shopify.sync.max_execution_time'));

        $store = $this->store;
        $limit = $this->params['limit'] ?? config('shopify.sync.limit');
        $order = $this->params['order'] ?? 'created_at asc';
        $connection = $this->connection;
        $created_at_min = $this->getCreatedAtMin($store);

        if (static::hasLockFor($store)) {
            $this->msg('is_locked', [], 'warning');
            return $this;
        }

        try {
            static::lock($store);

            // Determine how much there is to sync.
            $total = $this->getApiClient()
                ->products
                ->get(compact('created_at_min', 'order'), 'count')
                ['count'];
            $pages = range(1, ceil($total / $limit));
            $params = compact('order', 'limit', 'created_at_min');

            $page_job = new ImportStorePage($store, $pages, $params, $connection, $this->dryrun);

            // Fire job to sync the first page.
            $connection == 'sync'
                ? dispatch_now($page_job)
                : dispatch($page_job)->onConnection($connection);

            $verb = $connection == 'sync' ? 'first_page_dispatched' : 'first_page_queued';
            $this->msg($verb, [], 'info');
        } catch (ClientException $ce) {
            $this->handleClientException($ce);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this;
    }

    /**
     * @param Exception $e
     */
    public function failed(Exception $e)
    {
        static::unlock($store = $this->getStore());

        switch (true) {
            case $e instanceof MaxAttemptsExceededException:
                $data = [];
                $msg = "queue_max_attempts_failure";
                break;
            default:
                $data = Util::exceptionArr($e);
                $msg = "queue_failed";
        }

        $this->msg($msg, $data);
    }

    /**
     * @return string
     */
    protected function getCreatedAtMin(): string
    {
        // Determine if the created_at_min if not give or set to `last`
        if (empty($this->params['created_at_min']) || $this->params['created_at_min'] == 'last') {
            return $this->getStore()->last_product_import_at
                // The last time there was a successful sync
                ? $this->getStore()->last_product_import_at ->format('c')
                // First sync? Then use the store created at date!
                : $this->getStore()->store_created_at->format('c');
        }

        // We may explicitly set a created_at_min
        return $created_at_min = (new Carbon($this->params['created_at_min']))
            ->format('c');
    }

    /**
     * @param $ce
     */
    public function handleClientException($ce): void
    {
        static::unlock($this->getStore());

        $uninstallable = config('shopify.sync.uninstallable_codes');

        switch (true) {
            case in_array($status_code = Util::exceptionStatusCode($ce), $uninstallable):
                event(new UninstallSuggested($this->getStore(), $status_code));
                break;
            default:
                $this->msg('api_failed', Util::exceptionArr($ce));
        }
    }

    /**
     * @param Exception $e
     */
    public function handleException(Exception $e): void
    {
        static::unlock($this->getStore());

        $this->msg('failed', Util::exceptionArr($e));
    }
}
