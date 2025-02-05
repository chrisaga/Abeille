<?php

require_once __DIR__.'/../../../core/php/AbeilleLog.php';

define('corePhpDir', __DIR__.'/../../../core/php/');
define('devicesDir', __DIR__.'/../../../core/config/devices/'); // Abeille's supported devices
define('devicesLocalDir', __DIR__.'/../../../core/config/devices_local/'); // Unsupported/user devices

class AbeilleTools
{
    const cmdsDir = __DIR__.'/../../../core/config/commands/';
    const configDir = __DIR__.'/../../../core/config/';
    const logDir = __DIR__."/../../../../../log/";

    /**
     * Get Plugin Log Level.
     *
     * @param: pluginName: Nom du plugin
     * @return: int, niveau de log defini pour le plugin
     */
    public static function getPluginLogLevel($pluginName)
    {
        // var_dump( config::getLogLevelPlugin()["log::level::Abeille"] );
        // si debug:  {"100":"1","200":"0","300":"0","400":"0","1000":"0","default":"0"}
        // si info:   {"100":"0","200":"1","300":"0","400":"0","1000":"0","default":"0"}
        // si warning:{"100":"0","200":"0","300":"1","400":"0","1000":"0","default":"0"}
        // si error:  {"100":"0","200":"0","300":"0","400":"1","1000":"0","default":"0"}
        // si aucun:  {"100":"0","200":"0","300":"0","400":"0","1000":"1","default":"0"}
        // si defaut: {"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}
        $logLevelPluginJson = config::getLogLevelPlugin()["log::level::Abeille"];
        if ($logLevelPluginJson['100']) return 4;
        if ($logLevelPluginJson['200']) return 3;
        if ($logLevelPluginJson['300']) return 2;
        if ($logLevelPluginJson['400']) return 1;
        if ($logLevelPluginJson['1000']) return 0;
        if ($logLevelPluginJson['default']) return 1; // This one is set to 1 but should be found from conf
    }

    /**
     * Convert log level string to number to compare more easily.
     *
     * @param $loglevel
     * @return int
     */
    public static function getNumberFromLevel($loglevel)
    {
        $niveau = array(
            "NONE" => 0,
            "ERROR" => 1,
            "WARNING" => 2,
            "INFO" => 3,
            "DEBUG" => 4
        );

        $upperString = strtoupper(trim($loglevel));
        if (array_search($upperString, $niveau, false)) {
            return $niveau[$upperString];
        }
        #if logLevel is not found, then no log is allowed
        return "0";
    }

    /***
     * if loglevel is lower/equal than the app requested level then message is written
     *
     * @param string $log level of the message
     * @param string plugin Name
     * @param string logger name = script qui envoie le message
     * @param string le message lui meme
     * @param string $message
     */
    public static function deamonlogFilter($loglevel = 'NONE', $pluginName, $loggerName = 'Tools', $message = '')
    {
        if (strlen($message) < 1) return;
        if (self::getNumberFromLevel($loglevel) <= self::getPluginLogLevel($pluginName)) {
            $loglevel = strtolower(trim($loglevel));
            if ($loglevel == "warning")
                $loglevel = "warn";
            /* Note: sprintf("%-5.5s", $loglevel) to have vertical alignment. Log level truncated to 5 chars => error/warn/info/debug */
            fwrite(STDOUT, '['.date('Y-m-d H:i:s').']['.sprintf("%-5.5s", $loglevel).'] '.$message.PHP_EOL);
        }
    }

    /* Returns path for device JSON config file for given 'device'.
       If device is not found, returns false. */
    public static function getDevicePath($device) {
        /* Reminder:
           config/devices => devices officially supported by Abeille
           config/devices_local => user/custom devices not supported yet
         */

        /* Is that a supported device ? */
        $deviceFilename = devicesDir.$device.'/'.$device.'.json';
        if (file_exists($deviceFilename)) {
            log::add('Abeille', 'debug', 'getDevicePath('.$device.') => '.$deviceFilename);
            return $deviceFilename;
        }

        /* Is that an unsupported or user device ? */
        $deviceFilename = devicesLocalDir.$device.'/'.$device.'.json';
        if (file_exists($deviceFilename)) {
            log::add('Abeille', 'debug', 'getDevicePath('.$device.') => '.$deviceFilename);
            return $deviceFilename;
        }

        log::add('Abeille', 'debug', 'getDevicePath('.$device.') => NOT found');
        return false; // Not found
    }

    /**
     * Needed for Template Generation
     */
    public static function getJSonConfigFilebyCmd($cmd)
    {
        $cmdFilename = self::cmdsDir.$cmd.'.json';

        if (!is_file($cmdFilename)) {
            log::add('Abeille', 'error', 'getJSonConfigFilebyDevices: filename is not a file: '.$cmdFilename);
            return array();
        }

        $content = file_get_contents($cmdFilename);

        $cmdJson = json_decode($content, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            log::add('Abeille', 'error', 'getJSonConfigFilebyDevices: filename content is not a json: '.$content);
            return array();
        }

        return $cmdJson;
    }

    /*
     * Needed for Template Generation
     */
    public static function getJSonConfigFilebyDevicesTemplate($device = 'none')
    {
        // log::add('Abeille', 'debug', 'getJSonConfigFilebyDevicesTemplate start');

        $deviceFilename = AbeilleTools::getDevicePath($device);
        if ($deviceFilename === false) {
            log::add('Abeille', 'error', 'Nouvel équipement \''.$device.'\' inconnu. Utilisation de la config par défaut. [Modelisation]');
            $device = 'defaultUnknown';
            $deviceFilename = devicesDir.$device.'/'.$device.'.json';
        }

        $content = file_get_contents($deviceFilename);

        // Recupere le template master
        $deviceTemplate = json_decode($content, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            log::add('Abeille', 'error', 'L\'équipement \''.$device.'\' a un mauvais fichier JSON.');
            log::add('Abeille', 'debug', 'getJSonConfigFilebyDevices(): content='.$content);
            return;
        }

        // Basic Commands
        $deviceCmds = array();
        $deviceCmds += self::getJSonConfigFilebyCmd("IEEE-Addr");
        $deviceCmds += self::getJSonConfigFilebyCmd("Link-Quality");
        $deviceCmds += self::getJSonConfigFilebyCmd("Time-Time");
        $deviceCmds += self::getJSonConfigFilebyCmd("Time-TimeStamp");
        $deviceCmds += self::getJSonConfigFilebyCmd("Power-Source");
        $deviceCmds += self::getJSonConfigFilebyCmd("Short-Addr");
        $deviceCmds += self::getJSonConfigFilebyCmd("online");

        // Recupere les templates Cmd instanciées
        foreach ($deviceTemplate[$device]['Commandes'] as $cmd => $file) {
            if (substr($cmd, 0, 7) == "include") {
                $deviceCmds += self::getJSonConfigFilebyCmd($file);
            }
        }

        // Ajoute les commandes au master
        $deviceTemplate[$device]['Commandes'] = $deviceCmds;

        // log::add('Abeille', 'debug', 'getJSonConfigFilebyDevicesTemplate end');
        return $deviceTemplate;
    }

    /**
     * return the config list from a file located in core/config directory
     *
     * @param null $jsonFile
     * @return mixed|void
     */
    public static function getJSonConfigFiles($jsonFile = null)
    {
        $configDir = self::configDir;

        // self::deamonlog("debug", "Tools: loading file ".$jsonFile." in ".$configDir);
        $confFile = $configDir.$jsonFile;

        //file exists ?
        if (!is_file($confFile)) {
            log::add('Abeille', 'error', $confFile.' not found.');
            return;
        }
        // is valid json
        $content = file_get_contents($confFile);
        if (!is_json($content)) {
            log::add('Abeille', 'error', $confFile.' is not a valid json.');
            return;
        }

        $json = json_decode($content, true);

        // self::deamonlogFilter( "DEBUG", 'Abeille', 'Tools', "AbeilleTools: nb line ".strlen($content) );

        return $json;
    }

    /**
     * Get device config with device name located in core/config/devices/object.json
     *
     * @param null $device
     * @param Abeille logger name
     * @return bool|mixed|void
     */
    public static function getJSonConfigFilebyDevices($device = 'none', $logger = 'Abeille')
    {
        $deviceFilename = AbeilleTools::getDevicePath($device);
        // log::add('Abeille', 'debug', 'getJSonConfigFilebyDevices: devicefilename'.$deviceFilename);
        if ($deviceFilename === false) {
            // log::add('Abeille', 'error', 'getJSonConfigFilebyDevices: file not found devicefilename'.$deviceFilename);
            return;
        }

        $content = file_get_contents($deviceFilename);

        $deviceJson = json_decode($content, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            log::add('Abeille', 'error', 'getJSonConfigFilebyDevices: filename content is not a json: '.$content);
            return;
        }

        // log::add('Abeille', 'debug', 'getJSonConfigFilebyDevices: '.$device.' json found Tools: nb line '.strlen($content));
        return $deviceJson;
    }

    /**
     * Return filename ready to be search in devices directories
     *
     * @param string $filename
     * @return mixed|string*
     */
    public static function getTrimmedValueForJsonFiles($filename = "")
    {
        //remove lumi. from name as all xiaomi devices have a lumi. name
        //remove all space in names for easier filename handling
        $trimmed = strlen($filename) > 1 ? str_replace(' ', '', str_replace('lumi.', '', $filename)) : "";
        return $trimmed;
    }

    /*
     * Scan config/devices directory to load devices name
     *
     * @param string $logger
     * @return array of json devices name
     */
    public static function getDeviceNameFromJson($logger = 'Abeille')
    {
        $return = array();
        $dirCount = 0;
        $dirExcluded = 0;
        $fileMissing = 0;
        $fileIllisible = 0;
        $fileCount = 0;

        if (file_exists(devicesDir) == false) {
            log::add('Abeille', 'error', "Problème d'installation. Le chemin '...core/config/devices' n'existe pas.");
            return $return;
        }

        $dh = opendir(devicesDir);
        while (($dirEntry = readdir($dh)) !== false) {
            $dirCount++;

            if ( in_array($dirEntry, array(".", "..", "listeCompatibilite.php", "Template") ) ) {
                $dirExcluded++;
                continue;
            }

            $fullPath = devicesDir.$dirEntry.DIRECTORY_SEPARATOR.$dirEntry.".json";
            if (!file_exists($fullPath)) {
                log::add($logger, 'warning', "Fichier introuvable: ".$fullPath);
                $fileMissing++;
                continue;
            }

            try {
                $returnOneMore = array_keys(json_decode(file_get_contents($fullPath),1))[0];
            } catch (Exception $e) {
                log::add($logger, 'error', 'Impossible de lire le contenu du fichier '.$fullPath);
                $fileIllisible++;
                continue;
            }

            $return[] = $returnOneMore;
            $fileCount++;
        }

        log::add($logger, 'debug', "Nb repertoire template parcourus: ".$dirCount." dont ". $dirExcluded." exclus soit ".($dirCount-$dirExcluded).". ".$fileCount." fichiers template ajoutés à la liste, ".$fileMissing++." introuvables et ".$fileIllisible." templates illisibles." );
        return $return;
    }

    /**
     * Return an array with zigate configuration, used by daemon info and daemons
     *
     * @return array
     */
    public static function getParameters()
    {
        $return = array();
        $return['parametersCheck'] = 'ok'; // Ces deux variables permettent d'indiquer la validité des données.
        $return['parametersCheck_message'] = "";

        //Most Fields are defined with default values
        $return['AbeilleParentId'] = config::byKey('AbeilleParentId', 'Abeille', '1', 1);
        $return['zigateNb'] = config::byKey('zigateNb', 'Abeille', '1', 1);

        for ($i = 1; $i <= $return['zigateNb']; $i++) {
            $return['AbeilleType'.$i] = config::byKey('AbeilleType'.$i, 'Abeille', 'none', 1);
            $return['AbeilleSerialPort'.$i] = config::byKey('AbeilleSerialPort'.$i, 'Abeille', 'none', 1);
            $return['IpWifiZigate'.$i] = config::byKey('IpWifiZigate'.$i, 'Abeille', '', 1);
            $return['AbeilleActiver'.$i] = config::byKey('AbeilleActiver'.$i, 'Abeille', 'N', 1);
        }

        return $return;
    }

    /**
     * Check if any missing daemon and restart missing ones.
     *
     * @param $parameters
     * @param $running
     * @return mixed
     */
    public static function checkAllDaemons($parameters, $running)
    {
        //remove all message before each check
        AbeilleTools::clearSystemMessage($parameters,'all');

        $status['state'] = "ok";
        if (AbeilleTools::isMissingDaemons($parameters, $running) == true) {
            $status['state'] = "nok";
            AbeilleTools::restartMissingDaemons($parameters, $running);

            //After restart daemons, if still are missing, then no hope.
            if (AbeilleTools::isMissingDaemons($parameters, $running) == false) {
                $status['state'] = "ok"; // Finally missing ones restarted properly
            } else {
                /* There are still some missing daemons */
                $daemons = AbeilleTools::getMissingDaemons($parameters, $running);
                if (strlen($daemons) == 0) {
                    /* Ouahhh.. not normal */
                    AbeilleTools::clearSystemMessage($parameters, 'all');
                    $status['state'] = "ok";
                } else {
                    log::add('Abeille', 'debug', __CLASS__.':'.__FUNCTION__.':'.__LINE__.': missing daemons '.$daemons);
                    AbeilleTools::sendMessageToRuche($daemons, 'Démons manquants: '.$daemons);
                }
            }
        }

        log::add('Abeille', 'debug', "checkAllDaemons() => ".$status['state']);
        return $status;
    }

    /**
     * Get running processes for Abeille plugin
     *
     * @return array
     */
    public static function getRunningDaemons(): array
    {
        exec("pgrep -a php | awk '/Abeille(Parser|SerialRead|Cmd|Socat|Interrogate).php /'", $running);
        return $running;
    }

    /**
     * Get running processes for Abeille plugin
     *
     * @return array of array
     */
    public static function getRunningDaemons2(): array
    {
        exec("pgrep -a php | grep Abeille", $processes);
        $daemons = [];
        $running = 0;
        foreach ($processes as $line) {
            $lineArr = explode(" ", $line);
            if (strstr($line, "AbeilleCmd") != false) {
                $shortName = "Cmd";
                $running |= daemonCmd;
            } else if (strstr($line, "AbeilleParser") != false) {
                $shortName = "Parser";
                $running |= daemonParser;
            } else if (strstr($line, "AbeilleMonitor") != false) {
                $shortName = "Monitor";
                $running |= daemonMonitor;
            } else if (strstr($line, "AbeilleSerialRead") != false) {
                $net = $lineArr[3]; // Ex 'Abeille1'
                $zgNb = substr($net, 7);
                $shortName = "SerialRead".$zgNb;
                $running |= constant("daemonSerialRead".$zgNb);
            } else if (strstr($line, "AbeilleSocat") != false) {
                $net = $lineArr[3]; // Ex 'Abeille1'
                $zgNb = substr($net, 7);
                $shortName = "Socat".$zgNb;
                $running |= constant("daemonSocat".$zgNb);
            } else
                $shortName = "Unknown";
            $d = array(
                'pid' => $lineArr[0],
                'cmd' => substr($line, strpos($line, " ")),
                'shortName' => $shortName
            );
            $daemons[] = $d;
        }
        $return = array(
            'running' => $running, // 1 bit per running daemon
            'daemons' => $daemons, // Detail on each daemon
        );
        return $return;
    }

    /**
     * return an array with expected daemons and theirs status
     *
     * @param $parameters
     * @param $running
     * @return array
     */
    public
    static function diffExpectedRunningDaemons($parameters, $running): array
    {
        $nbZigate = $parameters['zigateNb'];
        $nbProcessExpected = 2; // parser and cmd

        $activedZigate = 0;
        $found['cmd'] = 0;
        $found['parser'] = 0;

        // Count number of processes we should have based on configuration, init $found['process x'] to 0.
        for ($n = 1; $n <= $nbZigate; $n++) {
            if ($parameters['AbeilleActiver'.$n] == "Y") {
                $activedZigate++;

                // e.g. "AbeilleType2":"WIFI", "AbeilleSerialPort2":"/dev/zigate2"
                // SerialRead + Socat
                if (stristr($parameters['AbeilleSerialPort'.$n], '/dev/zigate')) {
                    $nbProcessExpected += 2;
                    $found['serialRead'.$n] = 0;
                    $found['socat'.$n] = 0;
                }

                // e.g. "AbeilleType1":"USB", "AbeilleSerialPort1":"/dev/ttyUSB3"
                // SerialRead
                if (preg_match("(tty|monit)", $parameters['AbeilleSerialPort'.$n])) {
                    $nbProcessExpected++;
                    $found['serialRead'.$n] = 0;
                }
            }
        }
        $found['expected'] = $nbProcessExpected;

        // Search processes runnning and increase $found['process x'] each time we find one.
        foreach ($running as $line) {
            $match = [];
            if (preg_match('/AbeilleSerialRead.php Abeille([0-9]) /', $line, $match)) {
                $abeille = $match[1];
                if (stristr($line, "abeilleserialread.php"))
                    $found['serialRead'.$abeille]++;
            }
            elseif (preg_match('/AbeilleSocat.php \/dev\/zigate([0-9]) /', $line, $match)) {
                $abeille = $match[1];
                if (stristr($line, 'abeillesocat.php')) {
                    $found['socat'.$abeille]++;
                }
            }
            else {
                if (stristr($line, "abeilleparser.php"))
                    $found['parser']++;
                if (stristr($line, "abeillecmd.php"))
                    $found['cmd']++;
            }
        }

        //log::add('Abeille', 'debug', __CLASS__.':'.__FUNCTION__.':'.__LINE__.': nbExpected: '.$nbProcessExpected.', found: '.json_encode($found));

        return $found;
    }

    /**
     * @param $isCron   is cron daemon for this plugin started
     * @param $parameters (parametersCheck, parametersCheck_message, AbeilleParentId, zigateNb,
     * AbeilleType,AbeilleSerialPortX, IpWifiZigateX, AbeilleActiverX
     * @param $running
     * return true if any missing daemons
     */
    public
    static function isMissingDaemons($parameters, $running): bool
    {
        //no cron, no start requested
        // if (AbeilleTools::isAbeilleCronRunning() == false) {
        //     return false;
        // }

        $found = self::diffExpectedRunningDaemons($parameters, $running);
        $nbProcessExpected = $found['expected'];
        array_pop($found);
        $result = $nbProcessExpected - array_sum($found);
        // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.'::'.__FUNCTION__.':'.__LINE__.': '.($result == 0 ? 'false' : 'true').',   found: '.json_encode($found));
        //log::add('Abeille', 'Info', 'Tools:isMissingDaemons:'.($result==1?"Yes":"No"));
        if ($result > 1) {
            log::add('Abeille', 'Warning', 'Abeille: il manque au moins un processus pour gérer la zigate');
            // message::add("Abeille", "Danger,  il manque au moins un processus pour gérer la zigate");
        }

        return $result > 0;
    }

    /**
     * @param array $parameters
     * @param $running
     */
    public
    static function restartMissingDaemons(array $parameters, $running)
    {
        $lastLaunch = config::byKey('lastDeamonLaunchTime', 'Abeille', '');
        $found = self::diffExpectedRunningDaemons($parameters, $running);
        array_splice($found, -1, 1);
        //get socat first
        arsort($found);
        $found = array_reverse($found);
        // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__.' : lastLaunch: '.$lastLaunch.', found:'.json_encode($found));

        $missing = "";
        foreach ($found as $daemon => $value) {
            if ($value == 0) {
                AbeilleTools::sendMessageToRuche($daemon,'relance de '.$daemon);
                $missing .= ", $daemon";
                $cmd = self::getStartCommand($parameters, $daemon);
                // log::add('Abeille', 'info', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__.': restarting Abeille'.$daemon. '/'. $value);
                // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__ .': restarting  XXXXX  Abeille XXXXX'.$daemon.': '.$cmd);
                exec($cmd.' &');
            }

        }
        // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__.': missing daemons:'.$missing);
    }

    /**
     * Stop given daemons list.
     * Ex: $daemons = "AbeilleMonitor AbeilleCmd AbeilleParser"
     * @param string $daemons Deamons list to stop, space separated. Empty string = ALL
     */
    public static function stopDaemons($daemons = "") {
        log::add('Abeille', 'debug', "stopDaemons($daemons)");
        if ($daemons != "") {
            $daemonsArr = explode(" ", $daemons);
            $grep = "";
            foreach ($daemonsArr as $daemon) {
                if ($grep != "")
                    $grep .= "\|";
                $grep .= $daemon;
            }
            $cmd = "pgrep -a php | grep '".$grep."'";
        } else {
            $cmd = "pgrep -a php | grep Abeille";
        }
        // log::add('Abeille', 'debug', "stopDaemons() => cmd=".$cmd);
        exec($cmd, $running);

        $nbOfDaemons = sizeof($running);
        if ($nbOfDaemons != 0) {
            log::add('Abeille', 'debug', 'stopDaemons(): Arret de '.$nbOfDaemons.' démons');
            for ($i = 0; $i < $nbOfDaemons; $i++) {
// log::add('Abeille', 'debug', 'deamon_stopDaemonsstop(): running[i]='.$running[$i]);
                $arr = explode(" ", $running[$i]);
                $pid = $arr[0];
                exec("kill -s TERM ".$pid);
// log::add('Abeille', 'debug', 'stopDaemons(): kill -s TERM '.$pid);
            }
            /* Waiting until timeout that all daemons be ended */
            define ("timeout", 2000); // 2sec
            $running = array(); // Clear previous result.
            for ($t = 0; ($nbOfDaemons != 0) && ($t < timeout); $t+=500) {
                usleep(500000); // Sleep 500ms
                exec($cmd, $running);
                $nbOfDaemons = sizeof($running);
// log::add('Abeille', 'debug', 'stopDaemons(): LA'.$nbOfDaemons."=".json_encode($running));
            }
            if ($nbOfDaemons != 0) {
                log::add('Abeille', 'debug', 'stopDaemons(): '.$nbOfDaemons.' démons toujours actifs apres '.timeout.' ms');
                log::add('Abeille', 'debug', 'stopDaemons(): '.json_encode($running));
                for ($i = 0; $i < $nbOfDaemons; $i++) {
                    $arr = explode(" ", $running[$i]);
                    $pid = $arr[0];
                    exec("kill -s KILL ".$pid);
                }
            }
        } else
            log::add('Abeille', 'debug', 'stopDaemons(): Aucun démon actif');

        return true;
    }

    /**
     * Start daemons.
     * Ex: $daemons = "AbeilleMonitor AbeilleCmd AbeilleParser"
     * @param string $daemons Deamons list to start, space separated. Empty string = ALL
     */
    public static function startDaemons($config, $daemons = "") {
        if ($daemons == "") {
            /* Note: starting input daemons first to not loose any returned
               value/status as opposed to cmd being started first */
            for ($zgNb = 1; $zgNb <= $config['zigateNb']; $zgNb++) {
                if ($config['AbeilleActiver'.$zgNb] != "Y")
                    continue; // Zigate disabled

                if ($config['AbeilleType'.$zgNb] == "WIFI")
                    $daemons .= "AbeilleSocat".$zgNb;
                if ($daemons != "")
                    $daemons .= " ";
                $daemons .= "AbeilleSerialRead".$zgNb;
            }
            $daemons .= " AbeilleParser AbeilleCmd";

            /* Starting 'AbeilleMonitor' daemon too if required */
            /* Reread 'debug.json' to avoid PHP cache issues */
            if (file_exists(dbgFile)) {
                $dbgConfig = json_decode(file_get_contents(dbgFile), true);
                if (isset($dbgConfig["dbgMonitorAddr"])) {
                    $daemons .= " AbeilleMonitor";
                }
            }
        }
        log::add('Abeille', 'debug', "startDaemons(): ".$daemons);
        $daemonsArr = explode(" ", $daemons);
        foreach ($daemonsArr as $daemon) {
            $cmd = self::getStartCommand($config, $daemon);
            if ($cmd == "")
                log::add('Abeille', 'debug', "startDaemons(): ERROR, empty cmd for '".$daemon."'");
            else
                exec($cmd.' &');
        }
    }

    /**
     * (Re)start given daemons list.
     * Ex: $daemons = "AbeilleMonitor AbeilleCmd AbeilleParser"
     * @param string $daemons Deamons list to restart, space separated
     */
    public static function restartDaemons($parameters, $daemons = "") {
        if (AbeilleTools::stopDaemons($daemons) == false)
            return false; // Error

        AbeilleTools::startDaemons($parameters, $daemons);

        return true; // ok
    }

    /**
     * Returns cmd to start given daemon
     * @param $config
     * @param $daemonFile (ex: 'cmd', 'parser', serialread1'...)
     * @return String
     */
    public static function getStartCommand($param, $daemonFile): string
    {
        $nohup = "/usr/bin/nohup";
        $php = "/usr/bin/php";
        $nb = (preg_match('/[0-9]/', $daemonFile, $matches) != true ? "" : $matches[0]);
        $logLevel = log::convertLogLevel(log::getLogLevel('Abeille'));
        $logDir = self::logDir;

        unset($matches);
        $daemonFile = (preg_match('/[a-zA-Z]*/', $daemonFile, $matches) != true ? "" : strtolower($matches[0]));

        switch ($daemonFile) {
        case 'cmd':
        case 'abeillecmd':
            $daemonPhp = "AbeilleCmd.php";
            $logCmd = " >>".$logDir."AbeilleCmd.log 2>&1";
            $cmd = $nohup." ".$php." ".corePhpDir.$daemonPhp." ".$logLevel.$logCmd;
            break;
        case 'parser':
        case 'abeilleparser':
            $daemonPhp = "AbeilleParser.php";
            $daemonLog = " >>".$logDir."AbeilleParser.log 2>&1";
            $cmd = $nohup." ".$php." ".corePhpDir.$daemonPhp." ".$logLevel.$daemonLog;
            break;
        case 'serialread':
        case 'abeilleserialread':
            $daemonPhp = "AbeilleSerialRead.php";
            $daemonParams = 'Abeille'.$nb.' '.$param['AbeilleSerialPort'.$nb].' ';
            $daemonLog = $logLevel." >>".$logDir."AbeilleSerialRead".$nb.".log 2>&1";
            exec(system::getCmdSudo().'chmod 777 '.$param['AbeilleSerialPort'.$nb].' > /dev/null 2>&1');
            $cmd = $nohup." ".$php." ".corePhpDir.$daemonPhp." ".$daemonParams.$daemonLog;
            break;
        case 'socat':
        case 'abeillesocat':
            $daemonPhp = "AbeilleSocat.php";
            $daemonParams = $param['AbeilleSerialPort'.$nb].' '.$logLevel.' '.$param['IpWifiZigate'.$nb];
            $daemonLog = " >>".$logDir."AbeilleSocat".$nb.'.log 2>&1';
            $cmd = $nohup." ".$php." ".corePhpDir.$daemonPhp." ".$daemonParams.$daemonLog;
            break;
        case 'monitor':
        case 'abeillemonitor':
            $log = " >>".log::getPathToLog("AbeilleMonitor.log")." 2>&1";
            $cmd = $php." -r \"require '".corePhpDir."AbeilleMonitor.php'; monRun();\"".$log;
            break;
        default:
            $cmd = "";
        }
        return $cmd;
    }

    /**
     * send a message to a ruche according to the splitted daemonnameX
     * where daemonname and zigateNbr are extracted.
     *
     * @param $daemon
     */
    public
    static function sendMessageToRuche($daemon, $message = "")
    {
        $daemonName = (preg_match('/[a-zA-Z]*/', $daemon, $matches) != true ? $daemon : $matches[0]);
        unset($matches);
        $zigateNbr = (preg_match('/[0-9]/', $daemon, $matches) != true ? "1" : $matches[0]);
        $messageToSend = ($message == "") ? "" : "$daemonName: $message";
        // log::add('Abeille', 'debug', "Process Monitoring: " .__CLASS__.':'.__FUNCTION__.':'.__LINE__.' sending '.$messageToSend.' to zigate '.$zigateNbr);
        if ( strlen($messageToSend) > 2 ) {
        Abeille::publishMosquitto(queueKeyAbeilleToAbeille, priorityInterrogation,
            "Abeille$zigateNbr/0000/SystemMessage", $messageToSend);
        }
    }

    /**
     * clean messages displayed by all zigates if no parameters or all
     *
     * @param array $parameters jeedom Abeille's config
     * @param string $which number, from 1 to 9
     */
    public
    static function clearSystemMessage($parameters, $which = 'all')
    {
        if ($which == 'all') {
            for ($n = 1; $n <= $parameters['zigateNb']; $n++) {
                if ($parameters['AbeilleActiver'.$n] == "Y") {
                    // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__.' clearing zigate '.$n);
                    AbeilleTools::sendMessageToRuche("daemon$n", "");
                }
            }
        } else {
            // log::add('Abeille', 'debug', "Process Monitoring: ".__CLASS__.':'.__FUNCTION__.':'.__LINE__.' clearing zigate '.$which);
            AbeilleTools::sendMessageToRuche("daemon$which", "");
        }
    }

    public
    static function checkRequired($type, $zigateNumber)
    {
        if ($type == 'PI')
            self::checkGpio();
        else if ($type == 'WIFI')
            self::checkWifi($zigateNumber);
    }

    /**
     * @param $zigateNumber
     */
    public
    static function checkWifi($zigateNumber)
    {
        exec("command -v socat", $out, $ret);
        if ($ret != 0) {
            log::add('Abeille', 'error', 'socat n\'est  pas installé. zigate Wifi inutilisable. Installer socat depuis la partie wifi zigate de la page de configuration');
            log::add('Abeille', 'debug', 'socat => '.implode(', ', $out));
            message::add("Abeille", "Erreur, socat n\'est  pas installé. zigate Wifi inutilisable. Installer socat depuis la partie wifi zigate de la page de configuration");

        } else {
            log::add('Abeille', 'debug', __CLASS__.'::'.__FUNCTION__.' L:'.__LINE__.'Zigate Wifi active trouvée, socat trouvé');
        }
    }

    /**
     * return missing daemon comparing active zigate and running daemons
     *
     * @param array $parameters
     * @param $running
     *
     * @return string of comma separated missing daemon
     */
    public
    static function getMissingDaemons(array $parameters, $running): string
    {
        $found = self::diffExpectedRunningDaemons($parameters, $running);
        $missing = "";
        foreach ($found as $daemon => $value) {
            if ($value == 0) {
                $missing .= ", $daemon";
                AbeilleTools::sendMessageToRuche($daemon, "processus manquant: $daemon");
                log::add('Abeille', 'debug', __CLASS__.':'.__FUNCTION__.':'.__LINE__.': messageToRuche: '.$daemon.' manquant');
            }
        }
        if (strlen($missing) > 1) {
            return substr($missing, 2);
        } else {
            return "";
        }
    }

    /**
     * Reglages des pin du raspberry
     *
     * typiquement c'est a executer une seule fois, lors de l'installation des dépendances.
     * je l'isole ici en cas de besoin
     */
    public
    static function checkGpio()
    {
        exec("command -v gpio && gpio -v", $out, $ret);
        if ($ret != 0) {
            log::add('Abeille', 'error', 'WiringPi semble mal installé. PiZigate inutilisable.');
            log::add('Abeille', 'debug', 'gpio -v => '.implode(', ', $out));
        } else {
            log::add('Abeille', 'debug', 'AbeilleTools:checkGpio(): Une PiZigate active trouvée => configuration des GPIOs');
            exec("gpio mode 0 out; gpio mode 2 out; gpio write 2 1; gpio write 0 0; sleep 0.2; gpio write 0 1 &");
        }
    }

    /**
     * Returns true is Abeille's cron is running.
     *
     * @return true if running, else false
     */
    public static function isAbeilleCronRunning()
    {
        if (is_object(cron::byClassAndFunction('Abeille', 'deamon')) &
            (cron::byClassAndFunction('Abeille', 'deamon')->running())) {
            //log::add('Abeille', 'debug', 'isAbeilleCronRunning: le plugin est démarré.');
            return true;
        }
        return false;
    }

    /**
     * simple log function, extended in AbeilleDebug
     *
     * @param string $loglevel
     * @param string $message
     */
    function deamonlog($loglevel = 'NONE', $message = "")
    {
        log::add('Abeille', $loglevel, $message);
    }

}

// var_dump(AbeilleTools::getDeviceNameFromJson());

?>
