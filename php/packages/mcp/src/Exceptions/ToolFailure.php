<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Exceptions;

/**
 * A refusal meant for the agent to read: the block does not exist, the template failed on
 * line 12, the token cannot write. Thrown from a handler, it reaches the model as an error
 * result with this message — where any other exception is reported and, outside debug mode,
 * reaches the model as "something went wrong".
 */
class ToolFailure extends McpException {}
