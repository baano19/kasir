package com.barbershop.pos.data.local.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "services",
    indices = [Index("branchId")]
)
data class ServiceEntity(
    @PrimaryKey val id: Int,
    val name: String,
    val price: Int,
    val branchId: Int
)
