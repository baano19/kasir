package com.barbershop.pos.domain.repository

import android.content.Context
import android.os.Build
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.preferencesDataStore
import com.barbershop.pos.data.AuthPreferences
import com.barbershop.pos.data.remote.ApiService
import com.barbershop.pos.data.remote.dto.LoginRequest
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import java.util.*

private val Context.dataStore by preferencesDataStore(name = "auth_prefs")

class AuthRepository(
    private val context: Context,
    private val apiService: ApiService
) {
    fun getDeviceId(): String {
        return UUID.randomUUID().toString()
    }

    suspend fun login(username: String, password: String): Result<LoginSuccess> {
        return try {
            val response = apiService.login(LoginRequest(username, password))
            if (response.success && response.data != null) {
                val data = response.data
                context.dataStore.edit { prefs ->
                    prefs[AuthPreferences.USER_ID_KEY] = data.id
                    prefs[AuthPreferences.USERNAME_KEY] = data.username
                    prefs[AuthPreferences.NAME_KEY] = data.name
                    prefs[AuthPreferences.ROLE_KEY] = data.role
                    prefs[AuthPreferences.BRANCH_ID_KEY] = data.branch_id
                    prefs[AuthPreferences.MEAL_ALLOWANCE_KEY] = data.meal_allowance
                }
                Result.success(
                    LoginSuccess(
                        id = data.id,
                        name = data.name,
                        role = data.role,
                        branchId = data.branch_id,
                        mealAllowance = data.meal_allowance
                    )
                )
            } else {
                Result.failure(Exception(response.message ?: "Login gagal"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun logout() {
        context.dataStore.edit { prefs ->
            prefs.clear()
        }
    }

    fun isLoggedIn(): Flow<Boolean> {
        return context.dataStore.data.map { prefs ->
            prefs[AuthPreferences.USER_ID_KEY] != null
        }
    }

    fun getCurrentUser(): Flow<CurrentUser?> {
        return context.dataStore.data.map { prefs ->
            val userId = prefs[AuthPreferences.USER_ID_KEY] ?: return@map null
            CurrentUser(
                id = userId,
                username = prefs[AuthPreferences.USERNAME_KEY] ?: "",
                name = prefs[AuthPreferences.NAME_KEY] ?: "",
                role = prefs[AuthPreferences.ROLE_KEY] ?: "",
                branchId = prefs[AuthPreferences.BRANCH_ID_KEY] ?: 0,
                mealAllowance = prefs[AuthPreferences.MEAL_ALLOWANCE_KEY] ?: 0
            )
        }
    }

    suspend fun getCurrentUserSync(): CurrentUser? {
        return try {
            val prefs = context.dataStore.data.map { it }.firstOrNull() ?: return null
            val userId = prefs[AuthPreferences.USER_ID_KEY] ?: return null
            CurrentUser(
                id = userId,
                username = prefs[AuthPreferences.USERNAME_KEY] ?: "",
                name = prefs[AuthPreferences.NAME_KEY] ?: "",
                role = prefs[AuthPreferences.ROLE_KEY] ?: "",
                branchId = prefs[AuthPreferences.BRANCH_ID_KEY] ?: 0,
                mealAllowance = prefs[AuthPreferences.MEAL_ALLOWANCE_KEY] ?: 0
            )
        } catch (e: Exception) {
            null
        }
    }
}

data class LoginSuccess(
    val id: Int,
    val name: String,
    val role: String,
    val branchId: Int,
    val mealAllowance: Int
)

data class CurrentUser(
    val id: Int,
    val username: String,
    val name: String,
    val role: String,
    val branchId: Int,
    val mealAllowance: Int
)

// Extension for Flow.firstOrNull()
suspend inline fun <T> Flow<T>.firstOrNull(): T? {
    var value: T? = null
    this.collect { value = it }
    return value
}
