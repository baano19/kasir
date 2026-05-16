package com.barbershop.pos.domain.repository

import com.barbershop.pos.data.local.dao.TransactionDao
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.data.local.entity.TransactionEntity
import com.barbershop.pos.data.remote.ApiService
import com.barbershop.pos.data.remote.ConflictResolver
import com.barbershop.pos.data.remote.dto.PushSyncRequest
import com.barbershop.pos.data.remote.dto.TransactionSyncRequest
import kotlinx.coroutines.flow.Flow
import java.text.SimpleDateFormat
import java.util.*

class TransactionRepository(
    private val transactionDao: TransactionDao,
    private val apiService: ApiService,
    private val deviceId: String
) {
    fun getAllTransactions(): Flow<List<TransactionEntity>> {
        return transactionDao.getAllTransactions()
    }

    fun getTransactionsByUser(userId: Int): Flow<List<TransactionEntity>> {
        return transactionDao.getTransactionsByUser(userId)
    }

    fun getTransactionsByBranch(branchId: Int): Flow<List<TransactionEntity>> {
        return transactionDao.getTransactionsByBranch(branchId)
    }

    fun getTransactionsByDate(date: String): Flow<List<TransactionEntity>> {
        return transactionDao.getTransactionsByDate(date)
    }

    fun getPendingSyncCount(): Flow<Int> {
        return transactionDao.getPendingSyncCount()
    }

    suspend fun addTransaction(
        userId: Int,
        serviceName: String,
        amount: Int,
        notes: String = "",
        branchId: Int
    ): TransactionEntity {
        val transaction = TransactionEntity(
            userId = userId,
            serviceName = serviceName,
            amount = amount,
            notes = notes,
            createdAt = System.currentTimeMillis(),
            branchId = branchId,
            syncStatus = SyncStatus.PENDING,
            deviceId = deviceId
        )
        transactionDao.insertTransaction(transaction)
        return transaction
    }

    suspend fun updateTransaction(transaction: TransactionEntity) {
        val updated = transaction.copy(
            lastUpdated = System.currentTimeMillis(),
            syncStatus = SyncStatus.PENDING
        )
        transactionDao.updateTransaction(updated)
    }

    suspend fun deleteTransaction(localId: String) {
        transactionDao.deleteByLocalId(localId)
    }

    suspend fun syncPendingTransactions(): Result<Int> {
        return try {
            val pending = transactionDao.getTransactionsBySyncStatus(SyncStatus.PENDING)
            if (pending.isEmpty()) return Result.success(0)

            val requests = pending.map { tx ->
                TransactionSyncRequest(
                    localId = tx.localId,
                    remoteId = tx.remoteId,
                    user_id = tx.userId,
                    service_name = tx.serviceName,
                    amount = tx.amount,
                    notes = tx.notes,
                    created_at = tx.createdAt.toString(),
                    branch_id = tx.branchId,
                    updated_at = tx.lastUpdated,
                    device_id = tx.deviceId
                )
            }

            val response = apiService.pushSync(
                type = "push",
                request = PushSyncRequest(transactions = requests)
            )

            if (response.success && response.results != null) {
                response.results.transactions.forEach { result ->
                    transactionDao.updateSyncStatus(
                        result.localId,
                        SyncStatus.SYNCED,
                        result.remoteId
                    )
                }

                response.conflicts.filter { it.type == "transaction" }.forEach { conflict ->
                    val local = transactionDao.getByLocalId(conflict.localId)
                    if (local != null) {
                        val serverTx = response.results.transactions.find {
                            it.localId == conflict.localId
                        }
                        if (serverTx != null) {
                            val resolved = ConflictResolver.resolveTransactionConflict(
                                local,
                                com.barbershop.pos.data.remote.dto.TransactionDto(
                                    id = conflict.remoteId,
                                    user_id = local.userId,
                                    service_name = local.serviceName,
                                    amount = local.amount,
                                    notes = local.notes,
                                    created_at = local.createdAt.toString(),
                                    branch_id = local.branchId,
                                    updated_at = conflict.serverVersion
                                )
                            )
                            transactionDao.updateTransaction(resolved)
                        }
                    }
                }

                Result.success(requests.size)
            } else {
                pending.forEach {
                    transactionDao.updateSyncStatus(it.localId, SyncStatus.FAILED, null)
                }
                Result.failure(Exception(response.message ?: "Sync gagal"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun pullMasterData() {
        try {
            val response = apiService.pullSync()
            if (response.success && response.data != null) {
                val data = response.data
                data.transactions.forEach { tx ->
                    val existing = transactionDao.getByRemoteId(tx.id)
                    if (existing == null) {
                        transactionDao.insertTransaction(
                            TransactionEntity(
                                remoteId = tx.id,
                                userId = tx.user_id,
                                serviceName = tx.service_name,
                                amount = tx.amount,
                                notes = tx.notes ?: "",
                                createdAt = tx.created_at.toLongOrNull() ?: System.currentTimeMillis(),
                                branchId = tx.branch_id,
                                syncStatus = SyncStatus.SYNCED,
                                deviceId = deviceId
                            )
                        )
                    }
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
