package com.barbershop.pos.domain.repository

import com.barbershop.pos.data.local.dao.ExpenseDao
import com.barbershop.pos.data.local.entity.ExpenseEntity
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.data.remote.ApiService
import com.barbershop.pos.data.remote.ConflictResolver
import com.barbershop.pos.data.remote.dto.ExpenseSyncRequest
import com.barbershop.pos.data.remote.dto.PushSyncRequest
import kotlinx.coroutines.flow.Flow
import java.text.SimpleDateFormat
import java.util.*

class ExpenseRepository(
    private val expenseDao: ExpenseDao,
    private val apiService: ApiService,
    private val deviceId: String
) {
    fun getAllExpenses(): Flow<List<ExpenseEntity>> {
        return expenseDao.getAllExpenses()
    }

    fun getExpensesByCategory(category: String): Flow<List<ExpenseEntity>> {
        return expenseDao.getExpensesByCategory(category)
    }

    fun getExpensesByBranchAndCategory(branchId: Int, category: String): Flow<List<ExpenseEntity>> {
        return expenseDao.getExpensesByBranchAndCategory(branchId, category)
    }

    fun getPendingSyncCount(): Flow<Int> {
        return expenseDao.getPendingSyncCount()
    }

    suspend fun addExpense(
        userId: Int,
        category: String,
        amount: Int,
        notes: String = "",
        branchId: Int
    ): ExpenseEntity {
        val expense = ExpenseEntity(
            userId = userId,
            category = category,
            amount = amount,
            notes = notes,
            createdAt = System.currentTimeMillis(),
            branchId = branchId,
            syncStatus = SyncStatus.PENDING,
            deviceId = deviceId
        )
        expenseDao.insertExpense(expense)
        return expense
    }

    suspend fun updateExpense(expense: ExpenseEntity) {
        val updated = expense.copy(
            lastUpdated = System.currentTimeMillis(),
            syncStatus = SyncStatus.PENDING
        )
        expenseDao.updateExpense(updated)
    }

    suspend fun deleteExpense(localId: String) {
        expenseDao.deleteByLocalId(localId)
    }

    suspend fun getUserMealExpenseToday(userId: Int, date: String): ExpenseEntity? {
        return expenseDao.getUserMealExpenseToday(userId, date)
    }

    suspend fun syncPendingExpenses(): Result<Int> {
        return try {
            val pending = expenseDao.getExpensesBySyncStatus(SyncStatus.PENDING)
            if (pending.isEmpty()) return Result.success(0)

            val requests = pending.map { exp ->
                ExpenseSyncRequest(
                    localId = exp.localId,
                    remoteId = exp.remoteId,
                    user_id = exp.userId,
                    branch_id = exp.branchId,
                    category = exp.category,
                    amount = exp.amount,
                    notes = exp.notes,
                    created_at = exp.createdAt.toString(),
                    updated_at = exp.lastUpdated,
                    device_id = exp.deviceId
                )
            }

            val response = apiService.pushSync(
                type = "push",
                request = PushSyncRequest(expenses = requests)
            )

            if (response.success && response.results != null) {
                response.results.expenses.forEach { result ->
                    expenseDao.updateSyncStatus(
                        result.localId,
                        SyncStatus.SYNCED,
                        result.remoteId
                    )
                }

                response.conflicts.filter { it.type == "expense" }.forEach { conflict ->
                    val local = expenseDao.getByLocalId(conflict.localId)
                    if (local != null) {
                        val serverExp = response.results.expenses.find {
                            it.localId == conflict.localId
                        }
                        if (serverExp != null) {
                            val resolved = ConflictResolver.resolveExpenseConflict(
                                local,
                                com.barbershop.pos.data.remote.dto.ExpenseDto(
                                    id = conflict.remoteId,
                                    user_id = local.userId,
                                    branch_id = local.branchId,
                                    category = local.category,
                                    amount = local.amount,
                                    notes = local.notes,
                                    created_at = local.createdAt.toString(),
                                    updated_at = conflict.serverVersion
                                )
                            )
                            expenseDao.updateExpense(resolved)
                        }
                    }
                }

                Result.success(requests.size)
            } else {
                pending.forEach {
                    expenseDao.updateSyncStatus(it.localId, SyncStatus.FAILED, null)
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
                data.expenses.forEach { exp ->
                    val existing = expenseDao.getByRemoteId(exp.id)
                    if (existing == null) {
                        expenseDao.insertExpense(
                            ExpenseEntity(
                                remoteId = exp.id,
                                userId = exp.user_id,
                                category = exp.category,
                                amount = exp.amount,
                                notes = exp.notes ?: "",
                                createdAt = exp.created_at.toLongOrNull() ?: System.currentTimeMillis(),
                                branchId = exp.branch_id,
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
