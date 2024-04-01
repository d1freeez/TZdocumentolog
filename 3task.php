<!DOCTYPE html>
<html lang="en">
<head>
</head>
<body>
    <h1>Comments</h1>
	<?php
        function exec($comments) {
        $commMap = [];
    
        foreach ($comments as $comm) {
            $commMap[$comm[0]] = $comm;
        }
        $res = '';
        foreach ($comments as $comm) {
            if ($comm[1] == $comm[0]) {
                $res .= '<div>' . $comm[2];
                $res .= show($comm[0], $commMap, 1);
                $res .= '</div>';
            }
        }
        return $res;
        }

        function show($parId, $commMap, $glubina) {
            $res = '';
            foreach ($commMap as $comm) {
                if ($comm[1] == $parId && $comm[1] != $comm[0]) {
                    $res .= '<div style="margin-left: ' . ($glubina * 20) . 'px;">' . $comm[2];
                    $res .= show($comm[0], $commMap, $glubina + 1);
                    $res .= '</div>';
                }
            }
            return $res;
        }

        $comments = array( 
            array(1, 1, "Comment 1"), 
            array(2, 1, "Comment 2"), 
            array(3, 2, "Comment 3"), 
            array(4, 1, "Comment 4"), 
            array(5, 2, "Comment 5"), 
            array(6, 3, "Comment 6"), 
            array(7, 7, "Comment 7") 
        ); 

        echo exec($comments); 
    ?>
</body>
</html>
