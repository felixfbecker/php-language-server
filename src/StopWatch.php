<?php

namespace LanguageServer;

class StopWatch
{
    static private $timings = [];
    static private $starts = [];
    static private $counters = [];
    static private $max = [];
    static private $min = [];
    static private $maxlabels = [];
    
    public function reset()
    {
        self::$timings = [];
        self::$starts = [];
        self::$counters = [];        
        self::$max = [];        
        self::$min = [];        
        self::$maxlabels = [];        
    }

    public function start(string $name)
    {
        assert(!isset(self::$starts[$name]));

        self::$starts[$name] = microtime(true);
        if (!isset(self::$timings[$name])) {
            self::$timings[$name] = 0;
            self::$counters[$name] = 0;
            self::$max[$name] = PHP_INT_MIN;
            self::$min[$name] = PHP_INT_MAX;
        }
    }

    public function stop(string $name, string $label = null)
    {
        assert(isset(self::$starts[$name]));

        $time = microtime(true) - self::$starts[$name];
        self::$timings[$name] += $time;     
        self::$counters[$name]++;
        self::$min[$name] = min(self::$min[$name], $time);
        if ($time > self::$max[$name]) {
            self::$max[$name] = $time;
            self::$maxlabels[$name] = $label;
        }
        unset(self::$starts[$name]);
    }

    public function getTimings(): string
    {
        $total = 0;
        foreach(self::$timings as $timing) {
            $total += $timing;
        }

        $texts = ['', '--- StopWatch timings ----------------'];
        foreach(self::$timings as $name => $time) {
            $texts[] = $name . '*' . self::$counters[$name]
                . ': tot. ' . self::formatTime($time)
                . ', ' . self::formatPercent($time / $total)
                . ', avg. '. self::formatTime($time / self::$counters[$name])
                . ', min. '. self::formatTime(self::$min[$name])
                . ', max. '. self::formatTime(self::$max[$name])
                . (empty(self::$maxlabels[$name]) ? '' : '(' . self::$maxlabels[$name] . ')');
        }
        $texts[] = '--------------------------------------';
        return implode("\n", $texts);
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds >= 60) {
            return (int)($seconds / 60).'min';
        }
        else if ($seconds >= 1) {
            return (int)($seconds).'s';
        }
        else if ($seconds >= 0.001) {
            return (int)($seconds * 1000).'ms';
        }
        else if ($seconds >= 0.000001) {
            return (int)($seconds * 1000000).'us';
        }
        else {
            return '0';
        }
    }

    private function formatPercent(float $fraction): string
    {
        return number_format($fraction * 100, 1) . '%';        
    }
}