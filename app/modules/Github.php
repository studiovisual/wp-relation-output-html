<?php
Class GithubRlout {
    
    public function git_upload_file($commit){
        
        $repository = get_option('git_repository_rlout');
        
        if(!empty($repository)){
            
            $commands = array();
            
            $commands[] = 'cd ' . get_option("path_rlout");
            
            $commands[] = 'git init';
            
            $commands[] = 'git remote add origin ' . $repository;
            
            $commands[] = 'git add .';
            
            $commands[] = 'git commit -m "'. $commit .'" ';
            
            $commands[] = 'git push origin master -f';
            
            $command = implode(" && ", $commands);
            
            $process = proc_open(
                $command,
                array(
                    // STDIN.``
                    0 => array("pipe", "r"),
                    // STDOUT.
                    1 => array("pipe", "w"),
                    // STDERR.
                    2 => array("pipe", "w"),
                ),
                $pipes
            );
            if ($process === FALSE) {
                die();
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            // var_dump($stderr);
            // var_dump($stdout);
            
            // die();
        }
    }     
}