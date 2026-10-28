<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the AI classification of a support ticket fails, whether
 * because of a provider error, a timeout, or an invalid AI response.
 *
 * The original ticket record is always preserved when this is thrown.
 */
class SupportTicketClassificationException extends Exception {}
