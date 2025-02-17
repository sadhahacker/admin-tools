<?php


function assetLink(string $type, string $key)
{
    return asset(\Config::get("link.{$type}.{$key}"));
}

/**
 * Format success message/data into json success response.
 *
 * @param  string  $message  Success message
 * @param  array|string  $data  Data of the response
 * @param  int  $statusCode
 * @return HTTP json response
 */
function successResponse($message = '', $data = '', $statusCode = 200)
{
    $response = ['success' => true];

    // if message given
    if (! empty($message)) {
        $response['message'] = $message;
    }

    // If data given
    if (! empty($data)) {
        $response['data'] = $data;
    }

    return response()->json($response, $statusCode);
}

/**
 * Format the error message into json error response.
 *
 * @param  string|array  $message  Error message
 * @param  int  $statusCode
 * @return HTTP json response
 */
function errorResponse($message, $statusCode = 400)
{
    return response()->json(['success' => false, 'message' => $message], $statusCode);
}
