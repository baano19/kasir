package com.barbershop.pos.data.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.barbershop.pos.data.local.dao.TransactionDao
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.data.remote.ApiService
import java.lang.Exception

class SyncWorker(
    appContext: Context,
    workerParams: WorkerParameters,
    private val transactionDao: TransactionDao,
    private val apiService: ApiService
) : CoroutineWorker(appContext, workerParams) {

    override suspend fun doWork(): Result {
        val pendingTransactions = transactionDao.getTransactionsBySyncStatus(SyncStatus.PENDING)
        
        var hasError = false
        
        for (tx in pendingTransactions) {
            try {
                val response = apiService.syncTransaction(tx)
                if (response.success) {
                    transactionDao.updateSyncStatus(tx.localId, SyncStatus.SYNCED, response.remoteId)
                } else {
                    transactionDao.updateSyncStatus(tx.localId, SyncStatus.FAILED, null)
                    hasError = true
                }
            } catch (e: Exception) {
                // Network error, will retry based on WorkManager backoff policy
                return Result.retry()
            }
        }

        return if (hasError) Result.failure() else Result.success()
    }
}
