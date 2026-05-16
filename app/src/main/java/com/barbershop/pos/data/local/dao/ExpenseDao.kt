package com.barbershop.pos.data.local.dao

import androidx.room.*
import com.barbershop.pos.data.local.entity.ExpenseEntity
import com.barbershop.pos.data.local.entity.SyncStatus
import kotlinx.coroutines.flow.Flow

@Dao
interface ExpenseDao {
    @Query("SELECT * FROM expenses ORDER BY createdAt DESC")
    fun getAllExpenses(): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE userId = :userId ORDER BY createdAt DESC")
    fun getExpensesByUser(userId: Int): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE branchId = :branchId ORDER BY createdAt DESC")
    fun getExpensesByBranch(branchId: Int): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE category = :category ORDER BY createdAt DESC")
    fun getExpensesByCategory(category: String): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE branchId = :branchId AND category = :category ORDER BY createdAt DESC")
    fun getExpensesByBranchAndCategory(branchId: Int, category: String): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE syncStatus = :status ORDER BY createdAt DESC")
    suspend fun getExpensesBySyncStatus(status: SyncStatus): List<ExpenseEntity>

    @Query("SELECT * FROM expenses WHERE syncStatus = :status AND branchId = :branchId")
    suspend fun getExpensesBySyncStatusAndBranch(status: SyncStatus, branchId: Int): List<ExpenseEntity>

    @Query("SELECT * FROM expenses WHERE remoteId = :remoteId LIMIT 1")
    suspend fun getByRemoteId(remoteId: Int): ExpenseEntity?

    @Query("SELECT * FROM expenses WHERE localId = :localId LIMIT 1")
    suspend fun getByLocalId(localId: String): ExpenseEntity?

    @Query("SELECT * FROM expenses WHERE DATE(createdAt / 1000, 'unixepoch') = :date ORDER BY createdAt DESC")
    fun getExpensesByDate(date: String): Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses WHERE userId = :userId AND category = 'makan' AND DATE(createdAt / 1000, 'unixepoch') = :date")
    suspend fun getUserMealExpenseToday(userId: Int, date: String): ExpenseEntity?

    @Query("SELECT COUNT(*) FROM expenses WHERE syncStatus = :status")
    fun getPendingSyncCount(status: SyncStatus = SyncStatus.PENDING): Flow<Int>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertExpense(expense: ExpenseEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertExpenses(expenses: List<ExpenseEntity>)

    @Update
    suspend fun updateExpense(expense: ExpenseEntity)

    @Query("UPDATE expenses SET syncStatus = :status, remoteId = :remoteId, conflictResolved = false WHERE localId = :localId")
    suspend fun updateSyncStatus(localId: String, status: SyncStatus, remoteId: Int?)

    @Query("UPDATE expenses SET lastUpdated = :lastUpdated WHERE localId = :localId")
    suspend fun updateLastUpdated(localId: String, lastUpdated: Long)

    @Query("DELETE FROM expenses WHERE localId = :localId")
    suspend fun deleteByLocalId(localId: String)
}
