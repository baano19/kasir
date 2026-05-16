package com.barbershop.pos.data.sync

import android.content.Context
import androidx.work.*
import java.util.concurrent.TimeUnit

object SyncScheduler {
    private const val SYNC_WORK_TAG = "data_sync"
    private const val MASTER_DATA_WORK_TAG = "master_data_sync"

    fun schedulePeriodic(context: Context) {
        val syncRequest = PeriodicWorkRequestBuilder<SyncWorker>(
            15, TimeUnit.MINUTES
        ).addTag(SYNC_WORK_TAG)
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                WorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS
            )
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            "periodic_sync",
            ExistingPeriodicWorkPolicy.KEEP,
            syncRequest
        )
    }

    fun scheduleMasterDataSync(context: Context) {
        val masterDataRequest = OneTimeWorkRequestBuilder<MasterDataSyncWorker>()
            .addTag(MASTER_DATA_WORK_TAG)
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                WorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS
            )
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            "master_data_sync",
            ExistingWorkPolicy.KEEP,
            masterDataRequest
        )
    }

    fun triggerImmediateSync(context: Context) {
        val syncRequest = OneTimeWorkRequestBuilder<SyncWorker>()
            .addTag(SYNC_WORK_TAG)
            .setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED)
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            "immediate_sync",
            ExistingWorkPolicy.APPEND_OR_REPLACE,
            syncRequest
        )
    }

    fun cancelAllSync(context: Context) {
        WorkManager.getInstance(context).apply {
            cancelAllWorkByTag(SYNC_WORK_TAG)
            cancelAllWorkByTag(MASTER_DATA_WORK_TAG)
        }
    }
}
