// GraphQL schema with introspection ON and no depth or cost limit.
// Friends-of-friends recursion fans out into a DoS amplifier.

const { createSchema, createYoga } = require("graphql-yoga");
const db = require("./db");

const typeDefs = /* GraphQL */ `
  type User {
    id: Int!
    username: String!
    email: String!
    ssn: String!
    isAdmin: Boolean!
    friends: [User!]!
  }

  type Query {
    me(id: Int!): User
    users: [User!]!
    user(id: Int!): User
  }
`;

const resolvers = {
  Query: {
    me: (_p, { id }) => db.findById(id),
    users: () => db.all(),
    user: (_p, { id }) => db.findById(id),
  },
  User: {
    // No depth or complexity limit. Each User resolves friends back to other Users,
    // each of those resolves friends again, and so on. A deeply nested query
    // explodes into a huge resolver tree from a tiny request body.
    friends: (parent) => db.friendsOf(parent.id),
  },
};

const schema = createSchema({ typeDefs, resolvers });

// Introspection is ON by default in graphql-yoga. We do not disable it.
const yoga = createYoga({
  schema,
  graphqlEndpoint: "/graphql",
  landingPage: false,
  // No depth limit, no cost limit, no batching cap.
});

module.exports = { yoga };
