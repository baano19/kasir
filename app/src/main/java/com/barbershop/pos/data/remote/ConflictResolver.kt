package com.barbershop.pos.data.remote

import com.barbershop.pos.data.local.entity.ExpenseEntity
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.data.local.entity.TransactionEntity
import com.barbershop.pos.data.remote.dto.ConflictData
import com.barbershop.pos.data.remote.dto.TransactionDto
import com.barbershop.pos.data.remote.dto.ExpenseDto

object ConflictResolver {
    fun resolveTransactionConflict(
        local: TransactionEntity,
        serverVersion: TransactionDto
    ): TransactionEntity {
        val serverUpdatedAt = parseTimestamp(serverVersion.updated_at ?: serverVersion.created_at)

        return if (local.lastUpdated > serverUpdatedAt) {
            local.copy(syncStatus = SyncStatus.PENDING, conflictResolved = true)
        } else {
            local.copy(
                remoteId = serverVersion.id,
                syncStatus = SyncStatus.SYNCED,
                conflictResolved = true,
                lastUpdated = serverUpdatedAt
            )
        }
    }

    fun resolveExpenseConflict(
        local: ExpenseEntity,
        serverVersion: ExpenseDto
    ): ExpenseEntity {
        val serverUpdatedAt = parseTimestamp(serverVersion.updated_at ?: serverVersion.created_at)

        return if (local.lastUpdated > serverUpdatedAt) {
            local.copy(syncStatus = SyncStatus.PENDING, conflictResolved = true)
        } else {
            local.copy(
                remoteId = serverVersion.id,
                syncStatus = SyncStatus.SYNCED,
                conflictResolved = true,
                lastUpdated = serverUpdatedAt
            )
        }
    }

    private fun parseTimestamp(dateString: String): Long {
        return try {
            dateString.toLongOrNull() ?: System.currentTimeMillis()
        } catch (e: Exception) {
            System.currentTimeMillis()
        }
    }

    fun createConflictRecord(
        type: String,
        localId: String,
        remoteId: Int,
        reason: String
    ): ConflictData {
        return ConflictData(
            localId = localId,
            remoteId = remoteId,
            type = type,
            reason = reason,
            serverVersion = System.currentTimeMillis().toString()
        )
    }
}
