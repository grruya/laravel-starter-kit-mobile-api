# Clean Code Standards

**Note:** This file uses php syntax but its language agnostic so this rules apply for every language not just php

## Rules

- **Early Returns:** Avoid nested `if` statements. Use guard clauses (e.g., `if (!$condition) return;`) to keep the "happy path" at the lowest indentation level.
- **Simplicity:** Favor simplicity over "clever" code. Avoid recursion, deep callbacks, and complex abstractions unless strictly necessary.
- **Method Limits:** Methods should have a maximum of **4 parameters**.
- **State Validation:** Ensure methods or classes are not performed on unintended data states. (e.g., A `cancel()` method on a `Reservation` model must first check if the status allows cancellation, throwing an exception if not).
- **Scope:** Keep variables as close as possible to the block where they are consumed.
- **Try and Catch:** Catch only where you can meaningfully recover or decide a different outcome — otherwise let it bubble up.

## Writing Style

- Write code like reading instructions: a linear sequence of clear steps where each line explains what happens next, avoiding hidden logic, deep nesting, or unclear jumps in execution.
- Never place complex logic inside if parentheses. Extract complex conditions into descriptive boolean variables before the if statement.
- Name function so its predictable what they return, for example: applyClothing should be void, whatToWearToday should return same enum or string
- Never add comments to my code no matter what

## Example

```php
enum Clothing: string
{
    case Shirt = 'shirt';
    case Jacket = 'jacket';
    case Hoodie = 'hoodie';
}

enum WeatherCondition: string
{
    case Sunny = 'sunny';
    case Rain = 'rain';
    case Snow = 'snow';
}

final readonly class WeatherForecastDTO
{
    public function __construct(
        public int $temperature,
        public ?WeatherCondition $condition = null,
    ) {}
}

function whatToWearToday(): Clothing
{
    try {
      $weather = getForecastForToday();
    } catch (WeatherApiException $e) {
      $weather = getCachedWeather();
    }

    $temperature = $todaysWeather->temperature;

    if ($temperature > 30) return Clothing::Shirt;
    if ($temperature < 10) return Clothing::Jacket;

    return Clothing::Hoodie;
}
```
