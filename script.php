<?php

class AdServer {

    private $index;

    public function __construct() {
        $this->index = 0;
    }

    /**
     * Determine if the ad should be served
     * @param array $publisherKeyValues
     * @param string $advertiserConditions
     * @return bool
     */
    public function shouldAdBeServed(array $publisherKeyValues, string $advertiserConditions): bool
    {
        $advertiserConditions = str_replace(" and ", " && ", $advertiserConditions);
        $advertiserConditions = str_replace(" or ", " || ", $advertiserConditions);
        return $this->parseConditions($publisherKeyValues, $advertiserConditions);
    }

    /**
     * Evaluate input string
     * @param string $conditions
     * @return bool
     */
    private function parseConditions(array $keyValues, string $conditions, int $index = 0) : bool {
        $scopedEval = null;
        $max = strlen($conditions);
        $buffer = "";
        $expectedRelation = null;
        for($this->index = $index; $this->index < $max; $this->index++) {
            if($conditions[$this->index] == '(') {
                $leftOperand = $this->parseConditions($keyValues, $conditions, ++$this->index);

                if($scopedEval === null) {
                    $scopedEval = $leftOperand;
                } else if($expectedRelation != null) {
                    if($expectedRelation == 'and') {
                        $scopedEval = $scopedEval && $leftOperand;
                    } else if($expectedRelation == 'or') {
                        $scopedEval = $scopedEval || $leftOperand;
                    }
                }
            } else if($conditions[$this->index] == ')') {
                return $scopedEval;
            } else {
                $char = $conditions[$this->index];
                if(in_array($char, [
                    '<',
                    '>',
                    "=",
                ])) {
                    if(!array_key_exists($buffer, $keyValues)) {
                        return false;
                    }

                    // Value before operand
                    $valueFromKeyValues = $keyValues[$buffer];

                    // Skip one more char for <= and >= signs
                    $sign = $char;
                    if($this->index + 1 < $max && $char != '=' &&  $conditions[$this->index + 1] == "="){
                        $sign .= '=';
                        $this->index++;
                    }

                    // Get value after opreand (read string until space)
                    $comparisonValue = "";
                    for(++$this->index; $this->index < $max && $conditions[$this->index] != ' ' && $conditions[$this->index] != ')'; $this->index++) {
                        $comparisonValue .= $conditions[$this->index];
                    }

                    $expressionEval = null;
                    
                    if(is_numeric($comparisonValue)) {
                        if(!is_numeric($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with numeric value at position $this->index");
                        }

                        $comparisonValue = floatval($comparisonValue);

                        if($sign == '<') {
                            $expressionEval = $valueFromKeyValues < $comparisonValue;
                        } else if($sign == '>') {
                            $expressionEval = $valueFromKeyValues > $comparisonValue;
                        } else if($sign == '>=') {
                            $expressionEval = $valueFromKeyValues >= $comparisonValue;
                        } else if($sign == '<=') {
                            $expressionEval = $valueFromKeyValues <= $comparisonValue;
                        } else if ($sign == '=') {
                            $expressionEval = $valueFromKeyValues == $comparisonValue;
                        }
                    } else {
                        if(!is_string($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with string value at position $this->index");
                        }

                        if($sign != '=') {
                            throw new Exception("Cannot compare two strings with operator different then '=' at position $this->index");
                        }

                        $expressionEval = $valueFromKeyValues == $comparisonValue;
                    }

                    if($scopedEval === null) {
                        $scopedEval = $expressionEval;
                    } else if($expectedRelation != null) {
                        if($expectedRelation == 'and') {
                            $scopedEval = $scopedEval && $expressionEval;
                        } else if($expectedRelation == 'or') {
                            $scopedEval = $scopedEval || $expressionEval;
                        }
                    }

                    $buffer = "";
                } else if($char == '&') {
                    $expectedRelation = 'and';
                    $this->index++;
                } else if($char == '|') {
                    $expectedRelation = 'or';
                    $this->index++;
                } else {
                    if($conditions[$this->index] != ' ') {
                        $buffer .= $conditions[$this->index];
                    }
                }
            }
        }

        return $scopedEval;
    }
}

class Test {
    private array $data;

    public function __construct() {
        $this->data = [
            [
                ['age' => 25, 'category' => 'economics', 'color' => 'dark'],
                "(age=35 and category=programming) or (age=25 and category=economics)",
                true
            ],
        ];
    }
    
    public function test() {
        $server = new AdServer();
        foreach($this->data as $data) {
            $publisher = $data[0];
            $advertiser = $data[1];
            $expected = $data[2] ? 'true' : 'false';

            $output = $server->shouldAdBeServed($publisher, $advertiser); 

            echo(($output ? 'true' : 'false') . " = " . $expected . "\n");
        }
    }
}

(new Test())->test();

?>