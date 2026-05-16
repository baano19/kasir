package com.barbershop.pos.presentation.expense

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.barbershop.pos.domain.repository.AuthRepository
import com.barbershop.pos.domain.repository.ExpenseRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*
import javax.inject.Inject

@HiltViewModel
class ExpenseViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val expenseRepository: ExpenseRepository
) : ViewModel() {

    val currentUser = authRepository.getCurrentUser()
    val allExpenses = expenseRepository.getAllExpenses()
    val pendingSyncCount = expenseRepository.getPendingSyncCount()

    private val _addState = MutableStateFlow<AddExpenseState>(AddExpenseState.Idle)
    val addState: StateFlow<AddExpenseState> = _addState

    private val _selectedCategory = MutableStateFlow("operasional")
    val selectedCategory: StateFlow<String> = _selectedCategory

    fun setCategory(category: String) {
        _selectedCategory.value = category
    }

    fun addExpense(category: String, amount: String, notes: String = "") {
        if (category.isBlank() || amount.isBlank()) {
            _addState.value = AddExpenseState.Error("Kategori dan jumlah harus diisi")
            return
        }

        val amountInt = amount.toIntOrNull() ?: run {
            _addState.value = AddExpenseState.Error("Jumlah harus angka")
            return
        }

        _addState.value = AddExpenseState.Loading
        viewModelScope.launch {
            try {
                val user = authRepository.getCurrentUserSync()
                if (user != null) {
                    if (category == "makan") {
                        val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                        val existing = expenseRepository.getUserMealExpenseToday(user.id, today)
                        if (existing != null) {
                            _addState.value = AddExpenseState.Error("Sudah klaim makan hari ini")
                            return@launch
                        }
                        if (amountInt != user.mealAllowance) {
                            _addState.value = AddExpenseState.Error(
                                "Jumlah makan harus ${user.mealAllowance}"
                            )
                            return@launch
                        }
                    }

                    expenseRepository.addExpense(
                        userId = user.id,
                        category = category,
                        amount = amountInt,
                        notes = notes,
                        branchId = user.branchId
                    )
                    _addState.value = AddExpenseState.Success
                } else {
                    _addState.value = AddExpenseState.Error("User tidak ditemukan")
                }
            } catch (e: Exception) {
                _addState.value = AddExpenseState.Error(e.message ?: "Error")
            }
        }
    }

    fun deleteExpense(localId: String) {
        viewModelScope.launch {
            try {
                expenseRepository.deleteExpense(localId)
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    fun resetAddState() {
        _addState.value = AddExpenseState.Idle
    }
}

sealed class AddExpenseState {
    object Idle : AddExpenseState()
    object Loading : AddExpenseState()
    object Success : AddExpenseState()
    data class Error(val message: String) : AddExpenseState()
}
