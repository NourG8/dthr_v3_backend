<?php

namespace App\Traits;

trait ApiResponser
{
    /**
     * Build successfull response
     * @param string|array $data
     * @param int $code
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function response($data, $code)
    {
        return response()->json($data, $code);
    }

    /**
     * Build successfull response
     * @param string|array $data
     * @param int $code
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function successResponse($data, $code = 200)
    {
        return response()->json($data, $code);
    }

    /**
     * Build error response
     * @param string|array $data
     * @param int $code
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function errorResponse($data, $code)
    {
        return response()->json(['error' => $data, 'code' => $code], $code);
    }

    /**
     * Build error message
     * @param string|array $message
     * @param int $code
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function messageResponse($message, $code)
    {
        return response()->json(['message' => $message, 'code' => $code], $code);
    }
}
