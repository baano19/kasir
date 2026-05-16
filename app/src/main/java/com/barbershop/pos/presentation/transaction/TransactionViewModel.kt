package com.barbershop.pos.presentation.transaction

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.barbershop.pos.data.local.entity.TransactionEntity
import com.barbershop.pos.domain.repository.AuthRepository
import com.barbershop.pos.domain.repository.MasterDataRepository
import com.barbershop.pos.domain.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*
import javax.inject.Inject

@HiltViewModel
class TransactionViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val transactionRepository: TransactionRepository,
    private val masterDataRepository: MasterDataRepository
) : ViewModel() {

    val currentUser = authRepository.getCurrentUser()
    val services = masterDataRepository.getServicesByBranch(0)
    val transactions = transactionRepository.getAllTransactions()
    val pendingSyncCount = transactionRepository.getPendingSyncCount()

    private val _addState = MutableStateFlow<AddTransactionState>(AddTransactionState.Idle)
    val addState: StateFlow<AddTransactionState> = _addState

    fun addTransaction(serviceName: String, amount: String) {
        if (serviceName.isBlank() || amount.isBlank()) {
            _addState.value = AddTransactionState.Error("Layanan dan jumlah harus diisi")
            return
        }

        val amountInt = amount.toIntOrNull() ?: run {
            _addState.value = AddTransactionState.Error("Jumlah harus angka")
            return
        }

        _addState.value = AddTransactionState.Loading
        viewModelScope.launch {
            try {
                val user = authRepository.getCurrentUserSync()
                if (user != null) {
                    transactionRepository.addTransaction(
                        userId = user.id,
                        serviceName = serviceName,
                        amount = amountInt,
                        branchId = user.branchId
                    )
                    _addState.value = AddTransactionState.Success
                } else {
                    _addState.value = AddTransactionState.Error("User tidak ditemukan")
                }
            } catch (e: Exception) {
                _addState.value = AddTransactionState.Error(e.message ?: "Error")
            }
        }
    }

    fun deleteTransaction(localId: String) {
        viewModelScope.launch {
            try {
                transactionRepository.deleteTransaction(localId)
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    fun resetAddState() {
        _addState.value = AddTransactionState.Idle
    }
}

sealed class AddTransactionState {
    object Idle : AddTransactionState()
    object Loading : AddTransactionState()
    object Success : AddTransactionState()
    data class Error(val message: String) : AddTransactionState()
}
