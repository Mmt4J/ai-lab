<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Groq)]
#[Model('openai/gpt-oss-120b')]
class Summarizer implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a helpful assistant that summarizes text into concise bullet points. Return only the bullet points, one per line. Do not include any introductory text or formatting.';
    }
}
