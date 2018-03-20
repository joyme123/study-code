local num = redis.call('get','test_key')

if (num == 1) then
    local val = redis.call('set','test_key',0)
    return val
else
    local val = redis.call('set','test_key',1)
    return val
end