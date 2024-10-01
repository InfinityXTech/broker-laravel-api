<?php
 
namespace App\Rules;
 
use Illuminate\Contracts\Validation\Rule;
 
class IntegerRanges implements Rule
{
    private string $pattern;
    private array $parsed;

    /**
     * Creates an integer ranges rule
     * 
     * @param string $pattern Available ranges in form "...10,11,13-15,20..."
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;

        $parsed = [];
        foreach (explode(',', $pattern) as $line) {
            if (!preg_match('#\.\.\.(\d+)|(\d+)-(\d+)|(\d+)\.\.\.|(\d+)#is', $line, $matches)) {
                throw new \Exception('Failed to parse pattern: ' . $line);
            }
            if (is_numeric($matches[1] ?? false)) {
                $parsed[] = [PHP_INT_MIN, (int)$matches[1]];
            }
            else if (is_numeric($matches[2] ?? false) || is_numeric($matches[3] ?? false)) {
                $parsed[] = [(int)$matches[2], (int)$matches[3]];
            }
            else if (is_numeric($matches[4] ?? false)) {
                $parsed[] = [(int)$matches[4], PHP_INT_MAX];
            }
            else if (is_numeric($matches[5] ?? false)) {
                $parsed[] = [(int)$matches[5], (int)$matches[5]];
            }
            else throw new \Exception('Failed to parse pattern: ' . $line . print_r($matches, true));
        }
        $this->parsed = $parsed;
    }
    
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        foreach ($this->parsed as $rule) {
            if ($rule[0] <= $value && $value <= $rule[1]) {
                return true;
            }
        }
        return false;
    }
 
    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be in range: ' . $this->pattern;
    }
}