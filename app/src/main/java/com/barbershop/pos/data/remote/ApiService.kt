package com.barbershop.pos.data.remote

import com.barbershop.pos.data.remote.dto.*
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

interface ApiService {
    @POST("api/login.php")
    suspend fun login(@Body request: LoginRequest): LoginResponse

    @GET("api/sync.php")
    suspend fun pullSync(@Query("type") type: String = "pull"): PullSyncResponse

    @POST("api/sync.php")
    suspend fun pushSync(@Query("type") type: String = "push", @Body request: PushSyncRequest): PushSyncResponse
}
