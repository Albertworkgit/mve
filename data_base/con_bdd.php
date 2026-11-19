<?php
        
         // connexion à la bdd
         $con =new mysqli("localhost", "root", "", "data_mve");
         // vérifier la connexion
         if(!$con){die('Erreur de connexion: '.$con->connect_error());};
         // echo "Connexion reussi !";
    
    ?>

<?php