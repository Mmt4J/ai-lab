<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Groq)]
#[Model('openai/gpt-oss-120b')]
class ProductDescriptionGenerator implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are a professional copywriter. Write compelling product descriptions based on the provided name, keywords, and tone. 
        Return only the description - no extra commentary, labels, or formatting.
        INSTRUCTIONS;
    }
}
