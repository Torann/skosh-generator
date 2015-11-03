<?php

namespace Skosh;

class Event
{
    /**
     * The registered events.
     *
     * @var array
     */
    protected static $events = [];

    /**
     * The events that have been fired.
     *
     * @var array
     */
    protected static $fired = [];

    /**
     * Register a handler (a callback function) for an event.
     *
     * Each execution of the method will add an additional callback.
     * The callbacks are executed in the order they are defined.
     *
     * To only execute a callback once, irrespective of the number
     * of times the event is fired, call the method with a third
     * argument of TRUE.
     *
     * @param  string $name     The name of the event.
     * @param  mixed  $callback The callback function.
     * @param  bool   $once     Only fire the callback once.
     * @return void
     */
    public static function bind($name, $callback, $once = false)
    {
        static::$events[$name][] = [
            $once ? 'once' : 'always' => $callback
        ];
    }

    /**
     * Identical to the append method, except the event handler is
     * added to the start of the queue.
     *
     * @param  string $name     The name of the event.
     * @param  mixed  $callback The callback function.
     * @param  bool   $once     Only fire the callback once.
     * @return void
     */
    public static function insert($name, $callback, $once = false)
    {
        if (static::bound($name))
        {
            array_unshift(
                static::$events[$name],
                [$once ? 'once' : 'always' => $callback]
            );
        }
        else {
            static::bind($name, $callback, $once);
        }
    }

    /**
     * Trigger all callback functions for an event.
     *
     * The method returns an array containing the responses from all
     * of the event handlers (even empty responses).
     *
     * Returns NULL if the event has no handlers.
     *
     * @param  string $name The name of the event.
     * @param  array  $data The data passed to the event handlers.
     * @param  bool   $stop Return after the first non-empty response.
     * @return mixed
     */
    public static function fire($name, $data = [], $stop = false)
    {
        if (static::bound($name))
        {
            static::$fired[$name] = true;

            foreach (static::$events[$name] as $key => $value)
            {
                list($type, $callback) = each($value);

                $responses[] = $response =
                    call_user_func_array($callback, (array)$data);

                if ($type == 'once') {
                    unset(static::$events[$name][$key]);
                }

                if ($stop && !empty($response)) {
                    return $responses;
                }
            }
        }

        return isset($responses) ? $responses : null;
    }

    /**
     * Check if an event has fired.
     *
     * @param  string $name The name of the event.
     * @return bool
     */
    public static function fired($name)
    {
        return isset(static::$fired[$name]);
    }

    /**
     * De-register the handlers for an event.
     *
     * To remove the event handlers for a specific event, pass the
     * name of the event to the method. To remove all event handlers,
     * call the method without any arguments.
     *
     * @param  string $name The name of the event.
     * @return void
     */
    public static function unbind($name = null)
    {
        static::clear(static::$events, $name);
    }

    /**
     * Reset the flag indicating an event has fired.
     *
     * If called without an argument, the 'fired' flag for all events
     * will be cleared.
     *
     * @param  string $name The name of the event.
     * @return void
     */
    public static function reset($name = null)
    {
        static::clear(static::$fired, $name);
    }

    /**
     * Check if any callback functions are bound to an event.
     *
     * @param  string $name The name of the event.
     * @return bool
     */
    public static function bound($name)
    {
        return isset(static::$events[$name]);
    }

    /**
     * Remove an element from an array, or clear an entire array.
     *
     * @param  array  $array The array.
     * @param  string $name  The name of the element to clear.
     * @return void
     */
    protected static function clear(&$array, $name)
    {
        if ($name == null) {
            $array = [];
        }
        else {
            unset($array[$name]);
        }
    }
}