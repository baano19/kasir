package com.barbershop.pos.presentation.main

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.barbershop.pos.domain.repository.AuthRepository
import com.barbershop.pos.domain.repository.TransactionRepository
import com.barbershop.pos.domain.repository.ExpenseRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*
import javax.inject.Inject

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val transactionRepository: TransactionRepository,
    private val expenseRepository: ExpenseRepository
) : ViewModel() {

    val currentUser = authRepository.getCurrentUser()

    private val _dashboardState = MutableStateFlow<DashboardState>(DashboardState.Loading)
    val dashboardState: StateFlow<DashboardState> = _dashboardState

    private val _syncStatus = MutableStateFlow<SyncStatus>(SyncStatus.Idle)
    val syncStatus: StateFlow<SyncStatus> = _syncStatus

    init {
        loadDashboard()
    }

    private fun loadDashboard() {
        viewModelScope.launch {
            try {
                val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                val transactions = transactionRepository.getTransactionsByDate(today)
                val pendingSync = combine(
                    transactionRepository.getPendingSyncCount(),
                    expenseRepository.getPendingSyncCount()
                ) { tx, ex -> tx + ex }

                combine(transactions, pendingSync, currentUser) { txList, pending, user ->
                    val totalRevenue = txList.sumOf { it.amount }
                    val customerCount = txList.size
                    val barberCommission = (totalRevenue * 0.5).toInt()

                    DashboardState.Success(
                        totalRevenue = totalRevenue,
                        customerCount = customerCount,
                        barberCommission = barberCommission,
                        pendingSyncCount = pending,
                        userName = user?.name ?: ""
                    )
                }.collect { state ->
                    _dashboardState.value = state
                }
            } catch (e: Exception) {
                _dashboardState.value = DashboardState.Error(e.message ?: "Error loading dashboard")
            }
        }
    }

    fun syncNow() {
        _syncStatus.value = SyncStatus.Syncing
        viewModelScope.launch {
            try {
                transactionRepository.syncPendingTransactions()
                expenseRepository.syncPendingExpenses()
                _syncStatus.value = SyncStatus.Success("Sinkronisasi berhasil")
            } catch (e: Exception) {
                _syncStatus.value = SyncStatus.Failed(e.message ?: "Sync gagal")
            }
        }
    }
}

sealed class DashboardState {
    object Loading : DashboardState()
    data class Success(
        val totalRevenue: Int,
        val customerCount: Int,
        val barberCommission: Int,
        val pendingSyncCount: Int,
        val userName: String
    ) : DashboardState()
    data class Error(val message: String) : DashboardState()
}

sealed class SyncStatus {
    object Idle : SyncStatus()
    object Syncing : SyncStatus()
    data class Success(val message: String) : SyncStatus()
    data class Failed(val message: String) : SyncStatus()
}
