<?php
/**
 * Livia
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Livia/blob/master/LICENSE
*/

namespace CharlotteDunois\Livia\Exceptions;

/**
 * Has a descriptive message for a command not having proper format.
 */
class CommandFormatException extends FriendlyException {
    /**
     * @param \CharlotteDunois\Livia\CommandMessage  $message
     * @internal
     */
    function __construct(\CharlotteDunois\Livia\CommandMessage $message) {
        $prefix = $message->client->getGuildPrefix($message->message->guild);
        
        parent::__construct('Invalid command usage. The `'.$message->command->name.'` command\'s accepted format is: '.
        $message->command->usage($message->command->format, $prefix).'. Use '.\CharlotteDunois\Livia\Commands\Command::anyUsage('help '.$message->command->name, $prefix).' for more information.');
    }
}
