<?php

include(dirname(__FILE__) . "/../src/AdServer.php");

class Test {
    private array $data;

    public function __construct() {
        $this->data = [
            [
                ['age' => 25, 'category' => 'economics', 'color' => 'dark', 'name' => 'Ben'],
                "(age=35 and category=programming and (color=black or color=orange)) or (age=25 and category=economics and (color=blue or (name=Ben or name=Josh) or color=yellow))",
                true,
            ],
            [
                ['age' => 25, 'category' => 'programming', 'color' => 'dark', 'name' => 'Jasmine', 'gender' => 'female'],
                "(age<35 and (category=economics or (category=programming and color=dark)) and ((gender=male and name=Ben) or (gender=female and name=Jasmine)))",
                true
            ],
            [
                ['age' => 25, 'category' => 'programming', 'color' => 'dark', 'name' => 'Jasmine', 'gender' => 'female'],
                "(() and ()) or (age=25 and color=white)",
                true
            ],
        ];
    }
    
    public function test() {
        $server = new AdServer();

        $i = 0;
        foreach($this->data as $data) {
            $publisher = $data[0];
            $advertiser = $data[1];

            /** @var bool $expected  */
            $expected = $data[2];

            $output = $server->shouldAdBeServed($publisher, $advertiser); 

            if($expected === $output) {
                echo("\033[32m $i. Pass \033[0m \n");
            } else {
                echo("\033[31m $i. Fail \033[0m \n");
            }

            $i++;
        }
    }
}

?>