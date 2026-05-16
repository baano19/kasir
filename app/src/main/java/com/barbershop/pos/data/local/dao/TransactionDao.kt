package com.barbershop.pos.data.local.dao

import androidx.room.*
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.data.local.entity.TransactionEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface TransactionDao {
    @Query("SELECT * FROM transactions ORDER BY createdAt DESC")
    fun getAllTransactions(): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE userId = :userId ORDER BY createdAt DESC")
    fun getTransactionsByUser(userId: Int): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE branchId = :branchId ORDER BY createdAt DESC")
    fun getTransactionsByBranch(branchId: Int): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE syncStatus = :status ORDER BY createdAt DESC")
    suspend fun getTransactionsBySyncStatus(status: SyncStatus): List<TransactionEntity>

    @Query("SELECT * FROM transactions WHERE syncStatus = :status AND branchId = :branchId")
    suspend fun getTransactionsBySyncStatusAndBranch(status: SyncStatus, branchId: Int): List<TransactionEntity>

    @Query("SELECT * FROM transactions WHERE remoteId = :remoteId LIMIT 1")
    suspend fun getByRemoteId(remoteId: Int): TransactionEntity?

    @Query("SELECT * FROM transactions WHERE localId = :localId LIMIT 1")
    suspend fun getByLocalId(localId: String): TransactionEntity?

    @Query("SELECT * FROM transactions WHERE DATE(createdAt / 1000, 'unixepoch') = :date ORDER BY createdAt DESC")
    fun getTransactionsByDate(date: String): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE userId = :userId AND DATE(createdAt / 1000, 'unixepoch') = :date")
    fun getUserTransactionsByDate(userId: Int, date: String): Flow<List<TransactionEntity>>

    @Query("SELECT COUNT(*) FROM transactions WHERE syncStatus = :status")
    fun getPendingSyncCount(status: SyncStatus = SyncStatus.PENDING): Flow<Int>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTransaction(transaction: TransactionEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTransactions(transactions: List<TransactionEntity>)

    @Update
    suspend fun updateTransaction(transaction: TransactionEntity)

    @Query("UPDATE transactions SET syncStatus = :status, remoteId = :remoteId, conflictResolved = false WHERE localId = :localId")
    suspend fun updateSyncStatus(localId: String, status: SyncStatus, remoteId: Int?)

    @Query("UPDATE transactions SET lastUpdated = :lastUpdated WHERE localId = :localId")
    suspend fun updateLastUpdated(localId: String, lastUpdated: Long)

    @Query("DELETE FROM transactions WHERE localId = :localId")
    suspend fun deleteByLocalId(localId: String)

    @Query("DELETE FROM transactions WHERE remoteId IS NULL AND syncStatus = :status")
    suspend fun deleteFailedPending(status: SyncStatus = SyncStatus.FAILED)
}
