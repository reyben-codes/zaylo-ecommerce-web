import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiService {
  static const String baseUrl = 'http://10.0.2.2:8000/api';

  static Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'email': email, 'password': password}),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    throw Exception(data['message'] ?? 'Login failed.');
  }

  static Future<Map<String, dynamic>> getDashboard(String token) async {
    final response = await http.get(
      Uri.parse('$baseUrl/buyer/dashboard'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    throw Exception(data['message'] ?? 'Failed to load dashboard.');
  }

  static Future<Map<String, dynamic>> getProducts({
    String? category,
    String? gender,
    String? search,
    String? sort,
    int? perPage,
    String? token,
  }) async {
    final query = <String, String>{};

    if (category != null && category.isNotEmpty) {
      query['category'] = category;
    }

    if (gender != null && gender.isNotEmpty) {
      query['gender'] = gender;
    }

    if (search != null && search.isNotEmpty) {
      query['search'] = search;
    }

    if (sort != null && sort.isNotEmpty) {
      query['sort'] = sort;
    }

    if (perPage != null) {
      query['per_page'] = perPage.toString();
    }

    final uri = Uri.parse('$baseUrl/buyer/products')
        .replace(queryParameters: query);

    final headers = <String, String>{'Accept': 'application/json'};

    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    final response = await http.get(uri, headers: headers);

    final data = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    throw Exception(data['message'] ?? 'Failed to load products.');
  }

static Future<Map<String, dynamic>> getProduct({
  required int productId,
  required String token,
}) async {
  final response = await http.get(
    Uri.parse('$baseUrl/buyer/products/$productId'),
    headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    },
  );

  final data = jsonDecode(response.body) as Map<String, dynamic>;

  if (response.statusCode >= 200 && response.statusCode < 300) {
    return data;
  }

  throw Exception(
    data['message'] ?? 'Failed to load product details.',
  );
}

        static Future<Map<String, dynamic>> getShops({
    required String token,
  }) async {
    final response = await http.get(
      Uri.parse('$baseUrl/buyer/shops'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    throw Exception(data['message'] ?? 'Failed to load shops.');
  }
}
