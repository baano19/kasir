package com.barbershop.pos.data.local.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "users",
    indices = [Index("username")]
)
data class UserEntity(
    @PrimaryKey val id: Int,
    val username: String,
    val name: String,
    val role: String,
    val branchId: Int,
    val mealAllowance: Int = 0
)
