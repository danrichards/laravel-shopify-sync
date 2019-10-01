<?php

namespace Dan\Shopify\Laravel\Support;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Models\Order;
use Dan\Shopify\Laravel\Models\OrderItem;
use Dan\Shopify\Laravel\Models\Product;
use Dan\Shopify\Laravel\Models\Variant;
use Exception;
use Illuminate\Support\Str;
use More\Laravel\Model;

/**
 * Class Util
 */
class Util
{
    /**
     * Truncate body_html data if ridiculously long.
     *
     * @param array $data
     * @param Model|null $obj
     * @return bool|string
     */
    public static function fillProductBodyHtml(array $data, $obj = null)
    {
        return substr($data['body_html'] ?? '', 0, 65000);
    }

    /**
     * @param array $line_item_data
     * @param Variant $variant
     * @return bool
     */
    public static function filterOrderItemImport(array $line_item_data, Variant $variant = null)
    {
        $variant_model = config('shopify.products.variants.model');
        $variant = $variant ?: $variant_model::findByStoreVariantId(
            $variant_id = $line_item_data['variant_id'],
            $search_with_null_store = null,
            $with_trashed = true);

        return ! empty($line_item_data['product_id'])
            && ! empty($line_item_data['variant_id'])
            && optional($variant)->exists;
    }

    /**
     * @param array $order_data
     * @return bool
     */
    public static function filterOrderImport(array $order_data)
    {
        return ! empty($order_data['line_items']);
    }

    /**
     * @param array $product_data
     * @return bool
     */
    public static function filterProductImport(array $product_data)
    {
        return true;
    }

    /**
     * @param array $variant_data
     * @param Product|null $product
     * @param Variant|null $variant
     * @return bool
     */
    public static function filterVariantImport(array $variant_data)
    {
        return true;
    }

    /**
     * @param array $line_item_data
     * @param OrderItem $order_item
     * @param Variant $variant
     * @return bool
     */
    public static function filterOrderItemUpdate(array $line_item_data, OrderItem $order_item, Variant $variant)
    {
        return ! empty($line_item_data['product_id'])
            && ! empty($line_item_data['variant_id']);
    }

    /**
     * @param array $order_data
     * @param Order $order
     * @return bool
     */
    public static function filterOrderUpdate(array $order_data, Order $order)
    {
        return $order_data['id'] == $order->store_order_id;
    }

    /**
     * @param array $product_data
     * @param Product $product
     * @return bool
     */
    public static function filterProductUpdate(array $product_data, Product $product)
    {
        return $product_data['id'] == $product->store_product_id;
    }

    /**
     * @param array $variant_data
     * @param Product $product
     * @param Variant $variant
     * @return bool
     */
    public static function filterVariantUpdate(array $variant_data, Product $product, Variant $variant)
    {
        return $product->variants->contains('store_variant_id', $variant_data['id']);
    }

    /**
     * @param array $dictionary
     * @return array
     */
    public static function dictionaryToNameValues(array $dictionary)
    {
        return static::nameValuesMergeDictionary([], $dictionary);
    }

    /**
     * @param array $name_values
     * @return array
     */
    public static function nameValuesToDictionary(array $name_values)
    {
        return static::dictionaryMergeNameValues([], $name_values);
    }

    /**
     * @param array $data
     * @param array $map
     * @param string|Model $model
     * @return array
     */
    public static function mapData(array $data, array $map, $model): array
    {
        $mapped = [];

        $dates = is_object($model)
            ? $model->getDates()
            : (new $model)->getDates();

        foreach ($map as $key => $field) {
            $value = array_get($data, $key);
            $base = class_basename($model);
            $fill_mutator = "fill{$base}".Str::studly($field);

            switch (true) {
                // Set those troublesome ISO 8601 dates.
                case in_array($field, $dates):
                    $value = $value ? Carbon::parse($value) : $value;
                    break;
                case method_exists(new static, $fill_mutator):
                    $value = static::$fill_mutator($data, is_object($model) ? $model : null);
                    break;
            }

            $mapped[$field] = $value;
        }

        return $mapped;
    }

    /**
     * @param array $data
     * @param array $max_lengths
     * @return array
     */
    public static function truncateFields(array $data, array $max_lengths)
    {
        foreach ($max_lengths as $field => $max_length) {
            if (strlen($data[$field]) > $max_length) {
                $data[$field] = substr($data[$field], 0, $max_length - 3) . '...';
            }
        }
        return $data;
    }

    /**
     * @param array $name_values
     * @param array $dictionary
     * @return array
     */
    public static function nameValuesMergeDictionary(array $name_values, array $dictionary)
    {
        foreach ($name_values as &$name_value) {
            if (! isset($name_value['name'], $name_value['value'])) {
                continue;
            }

            if (array_key_exists($name_value['name'], $dictionary)) {
                $name_value['value'] = $dictionary[$name_value['name']];
                unset($dictionary[$name_value['name']]);
            }
        }

        // Whatever is left, goes on the end of the name_values array
        foreach ($dictionary as $name => $value) {
            array_push($name_values, compact('name', 'value'));
        }

        return $name_values;
    }

    /**
     * @param array $name_values
     * @param array $dictionary
     * @return array
     */
    public static function dictionaryMergeNameValues(array $dictionary, array $name_values)
    {
        foreach ($name_values as $name_value) {
            if (isset($dictionary[$name_value['name']])) {
                $dictionary[$name_value['name']] = $name_value['value'];
            } else {
                $dictionary[$name_value['name']] = $name_value['value'];
            }
        }

        return $dictionary;
    }

    /**
     * @param Exception $e
     * @param array|null $data
     * @param bool $include_trace
     * @return array
     */
    public static function exceptionArr(Exception $e, array $data = null, $include_trace = true)
    {
        $arr['exception'] = get_class($e);
        $arr['msg'] = $e->getMessage();
        $arr['file_line'] = sprintf("%s:%s", $e->getFile(), $e->getLine());

        if ($include_trace) {
            $arr['trace'] = static::exceptionTraceArr($e);
        }

        if (! is_null($data)) {
            $arr['data'] = $data;
        }

        $arr['response_msg'] = $e->getMessage();

        if (method_exists($e, 'getResponse')) {
            if (method_exists($response = $e->getResponse(), 'getBody')) {
                if (method_exists($body = $response->getBody(), 'getContents')) {
                    $arr['response_msg'] = $body->getContents();
                }
            }
        }

        return $arr;
    }

    /**
     * @param Exception $e
     * @param int $limit
     * @return array
     */
    public static function exceptionTraceArr(Exception $e, $limit = 20)
    {
        $trace = array_map(function($t) {
            return [
                'class' => isset($t['class']) ? $t['class'] : null,
                'function' => isset($t['function']) ? $t['function'] : null,
                'file' => isset($t['file']) ? $t['file'] : null,
                'line' => isset($t['line']) ? $t['line'] : null
            ];
        }, $e->getTrace());

        return array_slice($trace, 0, $limit);
    }

    /**
     * @param Exception $e
     * @return int|null
     */
    public static function exceptionStatusCode(Exception $e)
    {
        $pos = strpos($e->getMessage(), '[status code]');

        if ($pos !== false) {
            return intval(substr($e->getMessage(), $pos + 14, 3));
        }

        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            if (method_exists($response, 'getStatusCode')) {
                return $response->getStatusCode();
            }
        }

        return null;
    }
}
