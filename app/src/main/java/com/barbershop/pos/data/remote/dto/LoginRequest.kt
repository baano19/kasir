package com.barbershop.pos.data.remote.dto

data class LoginRequest(
    val username: String,
    val password: String
)

data class LoginResponse(
    val success: Boolean,
    val data: LoginData? = null,
    val message: String? = null
)

data class LoginData(
    val id: Int,
    val username: String,
    val name: String,
    val role: String,
    val branch_id: Int,
    val meal_allowance: Int = 0
)
