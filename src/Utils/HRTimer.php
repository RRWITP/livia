<?php
/**
 * Livia
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Livia/blob/master/LICENSE
*/

namespace CharlotteDunois\Livia\Utils;

class HRTimer {
    protected $hrtime;
    protected $nativeHrtime;
    
    protected $timer;
    protected $lastTime;
    
    /**
     * Constructor.
     */
    function __construct() {
        $this->hrtime = \extension_loaded('hrtime');
        $this->nativeHrtime = \function_exists('php_hrtime_current');
        
        if($this->hrtime) {
            $this->timer = new \HRTime\StopWatch();
        }
    }
    
    /**
     * Returns the resolution (the end product of 10^X, positive). Nano for hrtime (native and pecl), micro for fallback.
     * @return integer
     */
    function getResolution() {
        return ($this->nativeHrtime || $this->hrtime ? 1000000000 : 1000000);
    }
    
    /**
     * Starts the timer.
     * @return void
     */
    function start() {
        if($this->nativeHrtime) {
            $this->timer = \php_hrtime_current(true);
        } elseif($this->hrtime) {
            $this->timer->start();
        } else {
            $this->timer = \microtime(true);
        }
    }
    
    /**
     * Stops the timer and returns the elapsed time in their respective time unit.
     * @return int
     */
    function stop(): int {
        if($this->timer === null) {
            return 0;
        }
        
        if($this->nativeHrtime) {
            $elapsed = \php_hrtime_current(true) - $this->timer;
        } elseif($this->hrtime) {
            $this->timer->stop();
            $elapsed = $this->timer->getElapsedTime(\HRTime\Unit::NANOSECOND);
        } else {
            $elapsed = \microtime(true) - $this->timer;
            $elapsed = \ceil(($elapsed * $this->getResolution()));
        }
        
        return $elapsed;
    }
    
    /**
     * Returns the elapsed time in their respective time unit.
     * @return int
     */
    function time(): int {
        if($this->nativeHrtime) {
            if(!$this->lastTime) {
                $this->lastTime = $this->timer;
            }
            
            $time = \php_hrtime_current(true);
            $elapsed = $time - $this->lastTime;
            $this->lastTime = $time;
        } elseif($this->hrtime) {
            $this->timer->stop();
            $elapsed = $this->timer->getLastElapsedTime(\HRTime\Unit::NANOSECOND);
            $this->timer->start();
        } else {
            if(!$this->lastTime) {
                $this->lastTime = $this->timer;
            }
            
            $time = \microtime(true);
            $elapsed = $time - $this->lastTime;
            
            $this->lastTime = $time;
            $elapsed = \ceil(($elapsed * $this->getResolution()));
        }
        
        return $elapsed;
    }
};
