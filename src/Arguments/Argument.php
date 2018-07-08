<?php
/**
 * Livia
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Livia/blob/master/LICENSE
*/

namespace CharlotteDunois\Livia\Arguments;

/**
 * A fancy argument.
 *
 * @property \CharlotteDunois\Livia\LiviaClient               $client        The client which initiated the instance.
 * @property string                                           $key           Key for the argument.
 * @property string                                           $label         Label for the argument.
 * @property string                                           $prompt        Question prompt for the argument.
 * @property \CharlotteDunois\Livia\Types\ArgumentType|null   $type          Type of the argument.
 * @property int|float|null                                   $max           If type is integer or float, this is the maximum value of the number. If type is string, this is the maximum length of the string.
 * @property int|float|null                                   $min           If type is integer or float, this is the minimum value of the number. If type is string, this is the minimum length of the string.
 * @property mixed|null                                       $default       The default value for the argument.
 * @property bool                                             $infinite      Whether the argument accepts an infinite number of values.
 * @property callable|null                                    $validate      Validator function for validating a value for the argument. ({@see \CharlotteDunois\Livia\Types\ArgumentType::validate})
 * @property callable|null                                    $parse         Parser function to parse a value for the argument. ({@see \CharlotteDunois\Livia\Types\ArgumentType::parse})
 * @property callable|null                                    $emptyChecker  Empty checker function for the argument. ({@see \CharlotteDunois\Livia\Types\ArgumentType::isEmpty})
 * @property int                                              $wait          How long to wait for input (in seconds).
 */
class Argument implements \Serializable {
    protected $client;
    
    protected $key;
    protected $label;
    protected $prompt;
    protected $type;
    protected $max;
    protected $min;
    protected $default;
    protected $infinite;
    protected $validate;
    protected $parse;
    protected $emptyChecker;
    protected $wait;
    
    /**
     * Constructs a new Argument. Info is an array as following:
     *
     * ```
     * array(
     *   'key' => string, (Key for the argument)
     *   'label' => string, (Label for the argument, defaults to key)
     *   'prompt' => string, (First prompt for the argument when it wasn't specified)
     *   'type' => string, (Type of the argument, must be the ID of one of the registered argument types)
     *   'max' => int|float, (If type is integer or float this is the maximum value, if type is string this is the maximum length, optional)
     *   'min' => int|float, (If type is integer or float this is the minimum value, if type is string this is the minimum length, optional)
     *   'default' => mixed, (Default value for the argumen, must not be null, optional)
     *   'infinite' => bool, (Infinite argument collecting, defaults to false)
     *   'validate' => callable, (Validator function for the argument, optional)
     *   'parse' => callable, (Parser function for the argument, optional)
     *   'emptyChecker' => callable, (Empty checker function for the argument, optional)
     *   'wait' => int (How long to wait for input (in seconds)
     * )
     * ```
     *
     * @param \CharlotteDunois\Livia\LiviaClient    $client
     * @param array                                 $info
     * @throws \InvalidArgumentException
     */
    function __construct(\CharlotteDunois\Livia\LiviaClient $client, array $info) {
        $this->client = $client;
        
        $validator = \CharlotteDunois\Validation\Validator::make($info, array(
            'key' => 'required|string|min:1',
            'prompt' => 'required|string|min:1',
            'type' => 'string',
            'max' => 'integer|float',
            'min' => 'integer|float',
            'infinite' => 'boolean',
            'validate' => 'callable',
            'parse' => 'callable',
            'emptyChecker' => 'callable',
            'wait' => 'integer|min:1'
        ));
        
        try {
            $validator->throw();
        } catch (\RuntimeException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }
        
        if(empty($info['type']) && (empty($info['validate']) || empty($info['parse']))) {
            throw new \InvalidArgumentException('Argument type can not be empty if you don\'t implement and validate and parse function');
        }
        
        if(!empty($info['type']) && !$this->client->registry->types->has($info['type'])) {
            throw new \InvalidArgumentException('Argument type "'.$info['type'].'" is not registered');
        }
        
        $this->key = (string) $info['key'];
        $this->label = (!empty($info['label']) ? $info['label'] : $info['key']);
        $this->prompt = (string) $info['prompt'];
        $this->type = (!empty($info['type']) ? $this->client->registry->types->get($info['type']) : null);
        $this->max = $info['max'] ?? null;
        $this->min = $info['min'] ?? null;
        $this->default = $info['default'] ?? null;
        $this->infinite = (!empty($info['infinite']));
        $this->validate = (!empty($info['validate']) ? $info['validate'] : null);
        $this->parse = (!empty($info['parse']) ? $info['parse'] : null);;
        $this->emptyChecker = (!empty($info['emptyChecker']) ? $info['emptyChecker'] : null);
        $this->wait = (int) ($info['wait'] ?? 30);
    }
    
    /**
     * @throws \RuntimeException
     * @internal
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        throw new \RuntimeException('Unknown property \CharlotteDunois\Livia\Arguments\Argument::'.$name);
    }
    
    /**
     * @throws \RuntimeException
     * @internal
     */
    function __call($name, $args) {
        if(\property_exists($this, $name)) {
            $callable = $this->$name;
            if(\is_callable($callable)) {
                return $callable(...$args);
            }
        }
        
        throw new \RuntimeException('Unknown method \CharlotteDunois\Livia\Arguments\Argument::'.$name);
    }
    
    /**
     * @internal
     */
    function serialize() {
        $vars = \get_object_vars($this);
        
        unset($vars['client'], $vars['validate'], $vars['parse'], $vars['emptyChecker']);
        
        return \serialize($vars);
    }
    
    /**
     * @internal
     */
    function unserialize($vars) {
        if(\CharlotteDunois\Yasmin\Models\ClientBase::$serializeClient === null) {
            throw new \Exception('Unable to unserialize a class without ClientBase::$serializeClient being set');
        }
        
        $vars = \unserialize($vars);
        
        foreach($vars as $name => $val) {
            $this->$name = $val;
        }
        
        $this->client = \CharlotteDunois\Yasmin\Models\ClientBase::$serializeClient;
    }
    
    /**
     * Prompts the user and obtains the value for the argument. Resolves with an array of ('value' => mixed, 'cancelled' => string|null, 'prompts' => Message[], 'answers' => Message[]). Cancelled can be one of user, time and promptLimit.
     * @param \CharlotteDunois\Livia\CommandMessage     $message      Message that triggered the command.
     * @param string|string[]                           $value        Pre-provided value(s).
     * @param int|double                                $promptLimit  Maximum number of times to prompt for the argument.
     * @param \CharlotteDunois\Yasmin\Models\Message[]  $prompts      An array consisting of the prompts.
     * @param \CharlotteDunois\Yasmin\Models\Message[]  $answers      An array consisting of the answers.
     * @param bool|string|null                          $valid        Whether the last retrieved value was valid.
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function obtain(\CharlotteDunois\Livia\CommandMessage $message, $value, $promptLimit = \INF, array $prompts = array(), array $answers = array(), $valid = null) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($message, $value, $promptLimit, &$prompts, &$answers, $valid) {
            $empty = ($this->emptyChecker !== null ? $this->emptyChecker($value, $message, $this) : ($this->type !== null ? $this->type->isEmpty($value, $message, $this) : $value === null));
            if($empty && $this->default !== null) {
                return $resolve(array(
                    'value' => $this->default,
                    'cancelled' => null,
                    'prompts' => array(),
                    'answers' => array()
                ));
            }
            
            if($this->infinite) {
                if(!$empty && $value !== null) {
                    $this->parseInfiniteProvided($message, (\is_array($value) ? $value : array($value)), $promptLimit)->done($resolve, $reject);
                    return;
                }
                
                $this->obtainInfinite($message, array(), $promptLimit)->done($resolve, $reject);
                return;
            }
            
            if(!$empty && $valid === null) {
                $value = \trim($value);
                $validate = ($this->validate ? array($this, 'validate') : array($this->type, 'validate'))($value, $message, $this);
                if(!($validate instanceof \React\Promise\PromiseInterface)) {
                    $validate = \React\Promise\resolve($validate);
                }
                
                return $validate->then(function ($valid) use ($message, $value, $promptLimit, &$prompts, &$answers) {
                    if($valid !== true) {
                        return $this->obtain($message, $value, $promptLimit, $prompts, $answers, $valid);
                    }
                    
                    $parse = ($this->parse ? array($this, 'parse') : array($this->type, 'parse'))($value, $message, $this);
                    if(!($parse instanceof \React\Promise\PromiseInterface)) {
                        $parse = \React\Promise\resolve($parse);
                    }
                    
                    return $parse->then(function ($value) use (&$prompts, &$answers) {
                        return array(
                            'value' => $value,
                            'cancelled' => null,
                            'prompts' => $prompts,
                            'answers' => $answers
                        );
                    });
                })->done($resolve, $reject);
            }
            
            if(\count($prompts) > $promptLimit) {
                return $resolve(array(
                    'value' => null,
                    'cancelled' => 'promptLimit',
                    'prompts' => $prompts,
                    'answers' => $answers
                ));
            }
            
            if($empty && $value === null) {
                $reply = $message->reply($this->prompt.\PHP_EOL.
                    'Respond with `cancel` to cancel the command. The command will automatically be cancelled in '.$this->wait.' seconds.');
            } elseif($valid === false) {
                $reply = $message->reply('You provided an invalid '.$this->label.'.'.\PHP_EOL.
                    'Please try again. Respond with `cancel` to cancel the command. The command will automatically be cancelled in '.$this->wait.' seconds.');
            } elseif(\is_string($valid)) {
                $reply = $message->reply($valid.\PHP_EOL.
                    'Please try again. Respond with `cancel` to cancel the command. The command will automatically be cancelled in '.$this->wait.' seconds.');
            } else {
                $reply = \React\Promise\resolve(null);
            }
            
            // Prompt the user for a new value
            $reply->done(function ($msg) use ($message, $promptLimit, &$prompts, &$answers, $resolve, $reject) {
                if($msg !== null) {
                    $prompts[] = $msg;
                }
                
                // Get the user's response
                $message->message->channel->collectMessages(function ($msg) use ($message) {
                    return ($msg->author->id === $message->message->author->id);
                }, array(
                    'max' => 1,
                    'time' => $this->wait
                ))->then(function ($messages) use ($message, $promptLimit, &$prompts, &$answers) {
                    if($messages->count() === 0) {
                        return array(
                            'value' => null,
                            'cancelled' => 'time',
                            'prompts' => $prompts,
                            'answers' => $answers
                        );
                    }
                    
                    $msg = $messages->first();
                    $answers[] = $msg;
                    
                    $value = $msg->content;
                    
                    if(\mb_strtolower($value) === 'cancel') {
                        return array(
                            'value' => null,
                            'cancelled' => 'user',
                            'prompts' => $prompts,
                            'answers' => $answers
                        );
                    }
                    
                    $validate = ($this->validate ? array($this, 'validate') : array($this->type, 'validate'))($value, $message, $this);
                    if(!($validate instanceof \React\Promise\PromiseInterface)) {
                        $validate = \React\Promise\resolve($validate);
                    }
                    
                    return $validate->then(function ($valid) use ($message, $value, $promptLimit, &$prompts, &$answers) {
                        if($valid !== true) {
                            return $this->obtain($message, $value, $promptLimit, $prompts, $answers, $valid);
                        }
                        
                        $parse = ($this->parse ? array($this, 'parse') : array($this->type, 'parse'))($value, $message, $this);
                        if(!($parse instanceof \React\Promise\PromiseInterface)) {
                            $parse = \React\Promise\resolve($parse);
                        }
                        
                        return $parse->then(function ($value) use (&$prompts, &$answers) {
                            return array(
                                'value' => $value,
                                'cancelled' => null,
                                'prompts' => $prompts,
                                'answers' => $answers
                            );
                        });
                    });
                }, function ($error) use (&$prompts, &$answers) {
                    if($error instanceof \RangeException) {
                        return array(
                            'value' => null,
                            'cancelled' => 'time',
                            'prompts' => $prompts,
                            'answers' => $answers
                        );
                    }
                    
                    throw $error;
                })->done($resolve, $reject);
            }, $reject);
        }));
    }
    
    /**
     * Prompts the user infinitely and obtains the values for the argument. Resolves with an array of ('values' => mixed, 'cancelled' => string|null, 'prompts' => Message[], 'answers' => Message[]). Cancelled can be one of user, time and promptLimit.
     * @param \CharlotteDunois\Livia\CommandMessage     $message      Message that triggered the command.
     * @param string[]                                  $values       Pre-provided values.
     * @param int|double                                $promptLimit  Maximum number of times to prompt for the argument.
     * @param \CharlotteDunois\Yasmin\Models\Message[]  $prompts      An array consisting of the prompts.
     * @param \CharlotteDunois\Yasmin\Models\Message[]  $answers      An array consisting of the answers.
     * @param bool|string|null                          $valid        Whether the last retrieved value was valid.
     * @return \React\Promise\ExtendedPromiseInterface
     */
    protected function obtainInfinite(\CharlotteDunois\Livia\CommandMessage $message, array $values = array(), $promptLimit = \INF, array &$prompts = array(), array &$answers = array(), bool $valid = null) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($message, $values, $promptLimit, $prompts, $answers, $valid) {
            $value = null;
            if(!empty($values)) {
                $value = $values[(\count($values) - 1)];
            }
            
            $this->infiniteObtain($message, $value, $values, $promptLimit, $prompts, $answers, $valid)->then(function ($value) use ($message, $values, $promptLimit, $prompts, $answers, $resolve) {
                if(\is_array($value)) {
                    return $resolve($value);
                }
                
                $values[] = $value;
                return $this->obtainInfinite($message, $values, $promptLimit, $prompts, $answers);
            })->done($resolve, $reject);
        }));
    }
    
    protected function infiniteObtain(\CharlotteDunois\Livia\CommandMessage $message, $value, array &$values, $promptLimit, array &$prompts, array &$answers, $valid = null) {
        if($value === null) {
            $reply = $message->reply($this->prompt.\PHP_EOL.
                'Respond with `cancel` to cancel the command, or `finish` to finish entry up to this point.'.\PHP_EOL.
                'The command will automatically be cancelled in '.$this->wait.' seconds.');
        } elseif($valid === false) {
            $escaped = \str_replace('@', "@\u{200B}", \CharlotteDunois\Yasmin\Utils\DataHelpers::escapeMarkdown($value));
            
            $reply = $message->reply('You provided an invalid '.$this->label.', "'.(\mb_strlen($escaped) < 1850 ? $escaped : '[too long to show]').'". '.
                                        'Please try again.');
        } elseif(\is_string($valid)) {
            $reply = $message->reply($valid.\PHP_EOL.
                'Respond with `cancel` to cancel the command, or `finish` to finish entry up to this point.'.\PHP_EOL.
                'The command will automatically be cancelled in '.$this->wait.' seconds.');
        } else {
            $reply = \React\Promise\resolve(null);
        }
        
        return $reply->then(function ($msg) use ($message, &$values, $promptLimit, &$prompts, &$answers) {
            if($msg !== null) {
                $prompts[] = $msg;
            }
            
            if(\count($prompts) > $promptLimit) {
                return array(
                    'value' => null,
                    'cancelled' => 'promptLimit',
                    'prompts' => $prompts,
                    'answers' => $answers
                );
            }
            
            // Get the user's response
            return $message->message->channel->collectMessages(function ($msg) use ($message) {
                return ($msg->author->id === $message->message->author->id);
            }, array(
                'max' => 1,
                'time' => $this->wait
            ))->then(function ($messages) use ($message, &$values, $promptLimit, &$prompts, &$answers) {
                if($messages->count() === 0) {
                    return array(
                        'value' => null,
                        'cancelled' => 'time',
                        'prompts' => $prompts,
                        'answers' => $answers
                    );
                }
                
                $msg = $messages->first();
                $answers[] = $msg;
                
                $value = $msg->content;
                
                if(\mb_strtolower($value) === 'finish') {
                    return array(
                        'value' => $values,
                        'cancelled' => (\count($values) > 0 ? null : 'user'),
                        'prompts' => $prompts,
                        'answers' => $answers
                    );
                } elseif(\mb_strtolower($value) === 'cancel') {
                    return array(
                        'value' => null,
                        'cancelled' => 'user',
                        'prompts' => $prompts,
                        'answers' => $answers
                    );
                }
                
                $validate = ($this->validate ? array($this, 'validate') : array($this->type, 'validate'))($value, $message, $this);
                if(!($validate instanceof \React\Promise\PromiseInterface)) {
                    $validate = \React\Promise\resolve($validate);
                }
                
                return $validate->then(function ($valid) use ($message, $value, &$values, $promptLimit, &$prompts, &$answers) {
                    if($valid !== true) {
                        return $this->infiniteObtain($message, $value, $values, $promptLimit, $prompts, $answers, $valid);
                    }
                    
                    return ($this->parse ? array($this, 'parse') : array($this->type, 'parse'))($value, $message, $this);
                });
            }, function ($error) use (&$prompts, &$answers) {
                if($error instanceof \RangeException) {
                    return array(
                        'value' => null,
                        'cancelled' => 'time',
                        'prompts' => $prompts,
                        'answers' => $answers
                    );
                }
                
                throw $error;
            });
        });
    }
    
    /**
     * Parses the provided infinite arguments.
     * @param \CharlotteDunois\Livia\CommandMessage  $message      Message that triggered the command.
     * @param string[]                               $values       Pre-provided values.
     * @param int|double                             $promptLimit  Maximum number of times to prompt for the argument.
     * @param int                                    $i            Current index of current argument value.
     * @return \React\Promise\ExtendedPromiseInterface
     */
    protected function parseInfiniteProvided(\CharlotteDunois\Livia\CommandMessage $message, array $values = array(), $promptLimit = \INF, int $i = 0) {
        if(empty($values)) {
            return $this->obtainInfinite($message, array(), $promptLimit);
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($message, &$values, $promptLimit, $i) {
            $value = $values[$i];
            $val = null;
            
            $validate = ($this->validate ? array($this, 'validate') : array($this->type, 'validate'))($value, $message, $this);
            if(!($validate instanceof \React\Promise\PromiseInterface)) {
                $validate = \React\Promise\resolve($validate);
            }
            
            return $validate->then(function ($valid) use ($message, $value, $promptLimit, &$val) {
                if($valid !== true) {
                    $val = $valid;
                    $prompts = array();
                    $answers = array();
                    
                    return $this->obtainInfinite($message, array($value), $promptLimit, $prompts, $answers, $valid);
                }
                
                return ($this->parse ? array($this, 'parse') : array($this->type, 'parse'))($value, $message, $this);
            })->then(function ($value) use ($message, &$values, $promptLimit, $i, &$val) {
                if($val !== null) {
                    return $value;
                }
                
                $values[$i] = $value;
                $i++;
                
                if($i < \count($values)) {
                    return $this->parseInfiniteProvided($message, $values, $promptLimit, $i);
                }
                
                return array(
                    'value' => $values,
                    'cancelled' => null,
                    'prompts' => array(),
                    'answers' => array()
                );
            })->done($resolve, $reject);
        }));
    }
}
