package com.barbershop.pos.data.remote

import com.barbershop.pos.data.local.entity.TransactionEntity
import retrofit2.http.Body
import retrofit2.http.POST

interface ApiService {
    @POST("api/transactions/sync")
    suspend fun syncTransaction(@Body transaction: TransactionEntity): SyncResponse
}

data class SyncResponse(
    val success: Boolean,
    val remoteId: Int?,
    val message: String?
)
