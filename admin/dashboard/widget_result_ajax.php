<?php
include "../../config/db.php";

$id = intval($_GET['id']);

$widget = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM dashboard_widgets WHERE id=$id")
);

$query = $widget['final_query'];
$result = mysqli_query($conn, $query);

echo "<table id='dynamicTable' class='table table-bordered table-striped'>";
echo "<thead><tr>";

$fields = mysqli_fetch_fields($result);
foreach($fields as $field){
    echo "<th>{$field->name}</th>";
}
echo "</tr></thead><tbody>";

while($row = mysqli_fetch_assoc($result)){
    echo "<tr>";
    foreach($row as $value){
        echo "<td>$value</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";