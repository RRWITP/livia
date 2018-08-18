<?php
/**
 * Livia
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Livia/blob/master/LICENSE
*/

namespace CharlotteDunois\Livia\Providers;

/**
 * Loads and stores settings associated with guilds in a MySQL database. Requires the composer package react/mysql.
 */
class MySQLProvider extends SettingProvider {
    /** @var \React\MySQL\ConnectionInterface */
    protected $db;
    
    protected $listeners = array();
    
    /** @var \CharlotteDunois\Yasmin\Utils\Collection */
    protected $settings;
    
    /**
     * Constructs a new instance.
     * @param \React\MySQL\ConnectionInterface  $db
     */
    function __construct(\React\MySQL\ConnectionInterface $db) {
        $this->db = $db;
        
        $this->settings = new \CharlotteDunois\Yasmin\Utils\Collection();
        
        $this->listeners['commandPrefixChange'] = function ($guild, $prefix) {
            $this->set($guild, 'commandPrefix', $prefix);
        };
        $this->listeners['commandStatusChange'] = function ($guild, $command, $enabled) {
            $this->set($guild, 'command-'.$command->name, $enabled);
        };
        $this->listeners['groupStatusChange'] = function ($guild, $group, $enabled) {
            $this->set($guild, 'group-'.$group->id, $enabled);
        };
        $this->listeners['guildCreate'] = function ($guild) {
            $this->setupGuild($guild);
        };
        $this->listeners['commandRegister'] = function ($command) {
            foreach($this->settings as $guild => $settings) {
                if($guild !== 'global' && $this->client->guilds->has($guild) === false) {
                    continue;
                }
                
                $this->setupGuildCommand($guild, $command, $settings);
            }
        };
        $this->listeners['groupRegister'] = function ($group) {
            foreach($this->settings as $guild => $settings) {
                if($guild !== 'global' && $this->client->guilds->has($guild) === false) {
                    continue;
                }
                
                $this->setupGuildGroup($guild, $group, $settings);
            }
        };
    }
    
    /**
     * Returns the MySQL connection.
     * @return \React\MySQL\Connection
     */
    function getDB() {
        return $this->db;
    }
    
    /**
     * {@inheritdoc}
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function clear($guild) {
        $guild = $this->getGuildID($guild);
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($guild) {
            $this->settings->delete($guild);
            $this->runQuery('DELETE FROM `settings` WHERE `guild` = ?', array($guild))->done($resolve, $reject);
        }));
    }
    
    /**
     * Creates a new table row in the db for the guild, if it doesn't exist already - otherwise loads the row.
     * @param string|\CharlotteDunois\Yasmin\Models\Guild  $guild
     * @param array|\ArrayObject                           $settings
     * @return \React\Promise\ExtendedPromiseInterface
     * @throws \InvalidArgumentException
     */
    function create($guild, &$settings = array()) {
        $guild = $this->getGuildID($guild);
        
        return $this->runQuery('SELECT * FROM `settings` WHERE `guild` = ?', array($guild))->then(function ($result) use ($guild, &$settings) {
            if(empty($result->resultRows)) {
                $this->settings->set($guild, $settings);
                return $this->runQuery('INSERT INTO `settings` (`guild`, `settings`) VALUES (?, ?)', array($guild, \json_encode($settings)));
            } else {
                $this->loadRow($result->resultRows[0]);
            }
        });
    }
    
    /**
     * {@inheritdoc}
     * @return void
     */
    function destroy(): void {
        foreach($this->listeners as $event => $listener) {
            $this->client->removeListener($event, $listener);
        }
        
        $this->db->close();
    }
    
    /**
     * {@inheritdoc}
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function init(\CharlotteDunois\Livia\LiviaClient $client): \React\Promise\ExtendedPromiseInterface {
        $this->client = $client;
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            foreach($this->listeners as $event => $listener) {
                $this->client->on($event, $listener);
            }
            
            $this->runQuery('CREATE TABLE IF NOT EXISTS `settings` (`guild` VARCHAR(20) NOT NULL, `settings` TEXT NOT NULL, PRIMARY KEY (`guild`))')->then(function () {
                return $this->runQuery('SELECT * FROM `settings`')->then(function ($result) {
                    foreach($result->resultRows as $row) {
                        $this->loadRow($row);
                    }
                    
                    if($this->settings->has('global')) {
                        return null;
                    }
                    
                    return $this->create('global')->then(function () {
                        return null;
                    });
                });
            })->done($resolve, $reject);
        }));
    }
    
    /**
     * {@inheritdoc}
     * @return mixed
     */
    function get($guild, string $key, $defaultValue = null) {
        $guild = $this->getGuildID($guild);
        
        if($this->settings->get($guild) === null) {
            $this->client->emit('warn', 'Settings of specified guild is not loaded - loading row - returning default value');
            
            $this->create($guild);
            return $defaultValue;
        }
        
        $settings = $this->settings->get($guild);
        if(\array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        
        return $defaultValue;
    }
    
    /**
     * {@inheritdoc}
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function set($guild, string $key, $value) {
        $guild = $this->getGuildID($guild);
        
        if($this->settings->get($guild) === null) {
            return $this->create($guild)->then(function () use ($guild, $key, $value) {
                $settings = $this->settings->get($guild);
                $settings[$key] = $value;
            
                return $this->runQuery('UPDATE `settings` SET `settings` = ? WHERE `guild` = ?', array(\json_encode($settings), $guild));
            });
        }
        
        $settings = $this->settings->get($guild);
        $settings[$key] = $value;
        
        return $this->runQuery('UPDATE `settings` SET `settings` = ? WHERE `guild` = ?', array(\json_encode($settings), $guild));
    }
    
    /**
     * {@inheritdoc}
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function remove($guild, string $key) {
        $guild = $this->getGuildID($guild);
        
        if($this->settings->get($guild) === null) {
            $this->client->emit('warn', 'Settings of specified guild is not loaded - loading row');
            
            return $this->create($guild)->then(function () use ($guild, $key) {
                $settings = $this->settings->get($guild);
                unset($settings[$key]);
            
                return $this->runQuery('UPDATE `settings` SET `settings` = ? WHERE `guild` = ?', array(\json_encode($settings), $guild));
            });
        }
        
        $settings = $this->settings->get($guild);
        unset($settings[$key]);
        
        return $this->runQuery('UPDATE `settings` SET `settings` = ? WHERE `guild` = ?', array(\json_encode($settings), $guild));
    }
    
    /**
     * Loads all settings for a guild.
     * @param string|\CharlotteDunois\Yasmin\Models\Guild  $guild
     * @return void
     * @internal
     */
    function setupGuild($guild) {
        $guild = $this->getGuildID($guild);
        
        $settings = $this->settings->get($guild);
        if(!$settings) {
            $this->create($guild)->done(null, array($this->client, 'handlePromiseRejection'));
            return;
        }
        
        if($guild === 'global' && \array_key_exists('commandPrefix', $settings)) {
            $this->client->setCommandPrefix($settings['commandPrefix'], true);
        }
        
        foreach($this->client->registry->commands as $command) {
            $this->setupGuildCommand($guild, $command, $settings);
        }
        
        foreach($this->client->registry->groups as $group) {
            $this->setupGuildGroup($guild, $group, $settings);
        }
    }
    
    /**
     * Sets up a command's status in a guild from the guild's settings.
     * @param string|\CharlotteDunois\Yasmin\Models\Guild  $guild
     * @param \CharlotteDunois\Livia\Commands\Command      $command
     * @param array|\ArrayObject                           $settings
     * @return void
     * @internal
     */
    function setupGuildCommand($guild, \CharlotteDunois\Livia\Commands\Command $command, &$settings) {
        if(!isset($settings['command-'.$command->name])) {
            return;
        }
        
        $command->setEnabledIn(($guild !== 'global' ? $guild : null), $settings['command-'.$command->name]);
    }
    
    /**
     * Sets up a group's status in a guild from the guild's settings.
     * @param string|\CharlotteDunois\Yasmin\Models\Guild   $guild
     * @param \CharlotteDunois\Livia\Commands\CommandGroup  $group
     * @param array|\ArrayObject                            $settings
     * @return void
     * @internal
     */
    function setupGuildGroup($guild, \CharlotteDunois\Livia\Commands\CommandGroup $group, &$settings) {
        if(!isset($settings['group-'.$group->id])) {
            return;
        }
        
        $group->setEnabledIn(($guild !== 'global' ? $guild : null), $settings['group-'.$group->id]);
    }
    
    /**
     * Runs a SQL query. Resolves with the QueryResult instance.
     * @param string  $sql
     * @param array   $parameters  Parameters for the query - these get escaped
     * @return \React\Promise\ExtendedPromiseInterface
     * @see https://github.com/friends-of-reactphp/mysql/blob/master/src/QueryResult.php
     */
    function runQuery(string $sql, array $parameters = array()) {
        return $this->db->query($sql, $parameters);
    }
    
    /**
     * Processes a database row.
     * @param array  $row
     * @return void
     */
    protected function loadRow(array $row) {
        $settings = \json_decode($row['settings'], true);
        if($settings === null) {
            $this->client->emit('warn', 'MySQLProvider couldn\'t parse the settings stored for guild "'.$row['guild'].'". Error: '.\json_last_error_msg());
            return;
        }
        
        $settings = new \ArrayObject($settings, \ArrayObject::ARRAY_AS_PROPS);
        $this->settings->set($row['guild'], $settings);
        
        try {
            $this->setupGuild($row['guild']);
        } catch (\InvalidArgumentException $e) {
            $this->settings->delete($row['guild']);
            $this->runQuery('DELETE FROM `settings` WHERE `guild` = ?', array($row['guild']))->done(null, array($this->client, 'handlePromiseRejection'));
        }
    }
}
