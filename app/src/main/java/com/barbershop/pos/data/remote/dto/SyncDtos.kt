package com.barbershop.pos.data.remote.dto

data class PullSyncResponse(
    val success: Boolean,
    val data: PullSyncData? = null,
    val message: String? = null
)

data class PullSyncData(
    val branches: List<BranchDto> = emptyList(),
    val services: List<ServiceDto> = emptyList(),
    val users: List<UserDto> = emptyList(),
    val transactions: List<TransactionDto> = emptyList(),
    val expenses: List<ExpenseDto> = emptyList()
)

data class BranchDto(
    val id: Int,
    val name: String,
    val address: String? = null,
    val meal_allowance: Int = 0
)

data class ServiceDto(
    val id: Int,
    val name: String,
    val price: Int,
    val branch_id: Int
)

data class UserDto(
    val id: Int,
    val username: String,
    val name: String,
    val role: String,
    val branch_id: Int,
    val meal_allowance: Int = 0
)

data class TransactionDto(
    val id: Int,
    val user_id: Int,
    val service_name: String,
    val amount: Int,
    val notes: String? = null,
    val created_at: String,
    val branch_id: Int,
    val updated_at: String? = null
)

data class ExpenseDto(
    val id: Int,
    val user_id: Int,
    val branch_id: Int,
    val category: String,
    val amount: Int,
    val notes: String? = null,
    val created_at: String,
    val updated_at: String? = null
)

data class PushSyncRequest(
    val transactions: List<TransactionSyncRequest> = emptyList(),
    val expenses: List<ExpenseSyncRequest> = emptyList()
)

data class TransactionSyncRequest(
    val localId: String,
    val remoteId: Int? = null,
    val user_id: Int,
    val service_name: String,
    val amount: Int,
    val notes: String = "",
    val created_at: String,
    val branch_id: Int,
    val updated_at: Long,
    val device_id: String
)

data class ExpenseSyncRequest(
    val localId: String,
    val remoteId: Int? = null,
    val user_id: Int,
    val branch_id: Int,
    val category: String,
    val amount: Int,
    val notes: String = "",
    val created_at: String,
    val updated_at: Long,
    val device_id: String
)

data class PushSyncResponse(
    val success: Boolean,
    val results: PushSyncResults? = null,
    val conflicts: List<ConflictData> = emptyList(),
    val message: String? = null
)

data class PushSyncResults(
    val transactions: List<SyncResultItem> = emptyList(),
    val expenses: List<SyncResultItem> = emptyList()
)

data class SyncResultItem(
    val localId: String,
    val remoteId: Int
)

data class ConflictData(
    val localId: String,
    val remoteId: Int,
    val type: String, // "transaction" or "expense"
    val reason: String,
    val serverVersion: String
)
