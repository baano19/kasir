package com.barbershop.pos.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "branches")
data class BranchEntity(
    @PrimaryKey val id: Int,
    val name: String,
    val address: String = "",
    val mealAllowance: Int = 0
)
