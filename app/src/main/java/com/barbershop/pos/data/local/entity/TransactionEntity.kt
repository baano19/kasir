package com.barbershop.pos.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import java.util.UUID

@Entity(tableName = "transactions")
data class TransactionEntity(
    @PrimaryKey val localId: String = UUID.randomUUID().toString(),
    val remoteId: Int? = null,
    val userId: Int,
    val serviceName: String,
    val amount: Int,
    val createdAt: Long,
    val branchId: Int,
    val syncStatus: SyncStatus = SyncStatus.PENDING,
    val lastUpdated: Long = System.currentTimeMillis()
)
