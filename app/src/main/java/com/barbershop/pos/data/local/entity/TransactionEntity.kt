package com.barbershop.pos.data.local.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey
import java.util.UUID

@Entity(
    tableName = "transactions",
    indices = [
        Index("syncStatus"),
        Index("userId"),
        Index("branchId"),
        Index("createdAt"),
        Index("remoteId")
    ]
)
data class TransactionEntity(
    @PrimaryKey val localId: String = UUID.randomUUID().toString(),
    val remoteId: Int? = null,
    val userId: Int,
    val serviceName: String,
    val amount: Int,
    val notes: String = "",
    val createdAt: Long,
    val branchId: Int,
    val syncStatus: SyncStatus = SyncStatus.PENDING,
    val lastUpdated: Long = System.currentTimeMillis(),
    val deviceId: String = "",
    val conflictResolved: Boolean = false
)
