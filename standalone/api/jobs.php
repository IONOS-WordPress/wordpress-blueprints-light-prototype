<?php

$input = file_get_contents('php://input');
header('Content-Type: application/json');
$data = json_decode($input, true);

if (json_last_error() === JSON_ERROR_NONE) {
  echo json_encode([
    "status" => "success",
    "data" => $data,
  ]);
} else {
  http_response_code(500);
  echo json_encode(["error" => json_last_error_msg()]);
}