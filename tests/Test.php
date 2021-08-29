<?php

include(dirname(__FILE__) . "/../src/AdServer.php");

class Test {
    private array $data;

    public function __construct() {
        $this->data = [
            [
                "Test long expression with normal bracket structure",
                ['age' => 25, 'category' => 'economics', 'color' => 'dark', 'name' => 'Ben'],
                "(age=35 and category=programming and (color=black or color=orange)) or (age=25 and category=economics and (color=blue or (name=Ben or name=Josh) or color=yellow))",
                true,
            ],
            [
                'Test more complex brackets structure',
                ['age' => 25, 'category' => 'programming', 'color' => 'dark', 'name' => 'Jasmine', 'gender' => 'female'],
                "age<35 and (category=economics or (category=programming and color=dark)) and ((gender=male and name=Ben) or (gender=female and name=Jasmine))",
                true
            ],
            [
                'Test empty brackets will return true always as there are no conditions in them',
                ['age' => 25, 'category' => 'programming', 'color' => 'dark', 'name' => 'Jasmine', 'gender' => 'female'],
                "(() and ()) or (age=25 and color=white)",
                true
            ],
            [
                'Test numerical intervals edge case',
                ['age' => 35, 'category' => 'programming', 'color' => 'white', 'name' => 'Jasmine', 'gender' => 'female'],
                "age=[10-35] or category=economics or (color=dark and name=Ben)",
                true
            ],
            [
                'Test numerical intervals non-edge case',
                ['age' => 20, 'category' => 'programming', 'color' => 'white', 'name' => 'Jasmine', 'gender' => 'female'],
                "age=[10-35] or category=economics or (color=dark and name=Ben)",
                true
            ],
            [
                'Test non existing key in logical expression',
                ['age' => 35, 'category' => 'programming', 'color' => 'white', 'name' => 'Jasmine', 'gender' => 'female'],
                "(color=white or color=dark) and randomNonExistingKey=test",
                false
            ],
            [
                'Test mismatch between open and closed parenthesis',
                ['age' => 35, 'category' => 'programming', 'color' => 'white', 'name' => 'Jasmine', 'gender' => 'female'],
                "(color=white or color=dark) and randomNonExistingKey=test)",
                false
            ],
            [
                'Test failing string arrays',
                ['age' => 35, 'gender' => 'male', 'category' => 'programming', 'color' => 'dark', 'name' => 'Marko'],
                "age<=35 and (category=economics or (category=programming and color=dark)) and ((gender=male and name=Ben,John,Peter,Luka) or (gender=female and name=Jasmine,Marinna,Anya,Emma))",
                false,
            ],
            [
                'Test success string arrays',
                ['age' => 35, 'gender' => 'male', 'category' => 'programming', 'color' => 'dark', 'name' => 'Ben'],
                "age<=35 and (category=economics or (category=programming and color=dark)) and ((gender=male and name=Ben,John,Peter,Luka) or (gender=female and name=Jasmine,Marinna,Anya,Emma))",
                true,
            ],
        ];
    }
    
    public function test() {
        $server = new AdServer();

        $i = 0;
        foreach($this->data as $data) {
            $testDescription = $data[0];
            $publisher = $data[1];
            $advertiser = $data[2];
            $expected = $data[3];

            $output = $server->shouldAdBeServed($publisher, $advertiser); 

            if($expected === $output) {
                echo("\033[32m $i. Pass - $testDescription \033[0m \n");
            } else {
                echo("\033[31m $i. Fail - $testDescription \033[0m \n");
            }

            $i++;
        }
    }
}

?>